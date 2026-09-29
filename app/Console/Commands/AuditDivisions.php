<?php

namespace App\Console\Commands;

use App\Models\Division;
use App\Models\ProceedingsDocument;
use App\Models\UnresolvedName;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('vic:audit {--limit=10 : How many examples to show per failed check}')]
#[Description('Check imported divisions for integrity problems')]
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

        $overSeated = DB::table('divisions')
            ->join('houses', 'houses.id', '=', 'divisions.house_id')
            ->join('votes', 'votes.division_id', '=', 'divisions.id')
            ->groupBy('divisions.id', 'houses.seats')
            ->havingRaw('count(votes.id) > houses.seats')
            ->pluck('divisions.id');

        $checks = [
            'Recorded votes differ from printed totals' => Division::query()->whereIn('id', $mismatched)->withCount(['votes'])->get()
                ->map(fn (Division $division): string => $this->describe($division)." printed {$division->ayes_count}/{$division->noes_count}, {$division->votes_count} votes recorded"),
            'Unresolved voter names' => UnresolvedName::query()->whereNull('resolved_member_id')->with('division')->get()
                ->map(fn (UnresolvedName $name): string => $this->describe($name->division)." \"{$name->raw_name}\""),
            'Divisions flagged for review' => Division::query()->where('needs_review', true)->get()->map(fn (Division $division): string => $this->describe($division)),
            'More voters than seats' => Division::query()->whereIn('id', $overSeated)->get()->map(fn (Division $division): string => $this->describe($division)),
            'Documents that failed to import' => ProceedingsDocument::query()->whereNotNull('parse_error')->get()
                ->map(fn (ProceedingsDocument $document): string => "{$document->title}: {$document->parse_error}"),
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

    private function describe(Division $division): string
    {
        return sprintf('%s %s #%d/%d', $division->house->short_name, $division->sitting_date->toDateString(), $division->sitting_number, $division->sequence);
    }
}
