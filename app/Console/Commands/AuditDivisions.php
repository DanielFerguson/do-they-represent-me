<?php

namespace App\Console\Commands;

use App\Models\Division;
use App\Models\Policy;
use App\Models\ProceedingsDocument;
use App\Models\UnresolvedName;
use App\Models\Vote;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('vic:audit {--limit=10 : How many examples to show per failed check}')]
#[Description('Check imported divisions and published policies for integrity problems')]
class AuditDivisions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $mismatched = DB::table('divisions')
            ->leftJoin('votes', 'votes.division_id', '=', 'divisions.id')
            ->groupBy('divisions.id', 'divisions.ayes_count', 'divisions.noes_count')
            ->havingRaw("count(votes.id) filter (where votes.vote = 'aye') <> divisions.ayes_count")
            ->orHavingRaw("count(votes.id) filter (where votes.vote = 'no') <> divisions.noes_count")
            ->pluck('divisions.id');

        $seatHolders = DB::table('memberships')
            ->selectRaw('count(*)')
            ->whereColumn('memberships.house_id', 'divisions.house_id')
            ->whereColumn('memberships.starts_on', '<=', 'divisions.sitting_date')
            ->where(fn ($query) => $query->whereNull('memberships.ends_on')->orWhereColumn('memberships.ends_on', '>=', 'divisions.sitting_date'));

        $overSeated = DB::table('divisions')
            ->whereRaw('(select count(*) from votes where votes.division_id = divisions.id) > ('.$seatHolders->toSql().')', $seatHolders->getBindings())
            ->pluck('divisions.id');

        $unseatedVotes = DB::table('votes')
            ->join('divisions', 'divisions.id', '=', 'votes.division_id')
            ->whereNotExists(fn ($query) => $query->from('memberships')
                ->whereColumn('memberships.member_id', 'votes.member_id')
                ->whereColumn('memberships.house_id', 'divisions.house_id')
                ->whereColumn('memberships.starts_on', '<=', 'divisions.sitting_date')
                ->where(fn ($query) => $query->whereNull('memberships.ends_on')->orWhereColumn('memberships.ends_on', '>=', 'divisions.sitting_date')))
            ->pluck('votes.id');

        $checks = [
            'Recorded votes differ from printed totals' => Division::query()->whereIn('id', $mismatched)->withCount(['votes'])->get()
                ->map(fn (Division $division): string => $this->describe($division)." printed {$division->ayes_count}/{$division->noes_count}, {$division->votes_count} votes recorded"),
            'Unresolved voter names' => UnresolvedName::query()->whereNull('resolved_member_id')->with('division')->get()
                ->map(fn (UnresolvedName $name): string => $this->describe($name->division)." \"{$name->raw_name}\""),
            'Divisions flagged for review' => Division::query()->where('needs_review', true)->get()->map(fn (Division $division): string => $this->describe($division)),
            'More voters than members holding a seat' => Division::query()->whereIn('id', $overSeated)->get()->map(fn (Division $division): string => $this->describe($division)),
            'Votes by members who held no seat in that house that day' => Vote::query()->whereIn('id', $unseatedVotes)->with(['division.house', 'member'])->get()
                ->map(fn (Vote $vote): string => $this->describe($vote->division)." {$vote->member->display_name}"),
            'Documents that failed to import' => ProceedingsDocument::query()->whereNotNull('parse_error')->get()
                ->map(fn (ProceedingsDocument $document): string => "{$document->title}: {$document->parse_error}"),
            'Published questions with nothing to compare' => collect($this->emptyPublishedPolicies()),
        ];

        $this->line(sprintf('%d divisions, %d votes, %d documents.', Division::query()->count(), DB::table('votes')->count(), ProceedingsDocument::query()->count()));

        $failed = 0;

        foreach ($checks as $check => $problems) {
            if ($problems->isEmpty()) {
                $this->line("  ✓ {$check}");

                continue;
            }

            $failed++;
            $this->warn("  ✗ {$check}: {$problems->count()}");
            $problems->take($limit)->each(fn (string $problem) => $this->line("      {$problem}"));
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Published policies with no linked division, or on which no party has
     * either a figure or a reviewers' note, so the quiz would have nothing
     * to compare a voter's answer with.
     *
     * @return list<string>
     */
    private function emptyPublishedPolicies(): array
    {
        return Policy::query()
            ->published()
            ->withCount('policyDivisions')
            ->with(['agreements' => fn ($query) => $query->where('subject_type', 'party')->whereNotNull('agreement')])
            ->orderBy('number')
            ->get()
            ->filter(fn (Policy $policy): bool => $policy->policy_divisions_count === 0
                || ($policy->agreements->isEmpty() && collect($policy->display_notes ?? [])->where('subject_type', 'party')->isEmpty()))
            ->map(fn (Policy $policy): string => sprintf('P%02d %s: %s', $policy->number, $policy->title, $policy->policy_divisions_count === 0 ? 'no linked divisions' : 'no party has a figure'))
            ->values()
            ->all();
    }

    private function describe(Division $division): string
    {
        return sprintf('%s %s #%d/%d', $division->house->short_name, $division->sitting_date->toDateString(), $division->sitting_number, $division->sequence);
    }
}
