<?php

namespace App\Domain\Policies;

use App\Domain\Stances\SubjectStance;
use App\Enums\VoteValue;
use App\Models\DivisionPartyPosition;
use App\Models\Party;
use App\Models\Policy;
use App\Models\PolicyAgreement;
use App\Models\PolicyDivision;
use App\Models\Vote;
use Illuminate\Support\Str;

/**
 * Everything a policy's public evidence page shows: its text and sources,
 * each party's stance, and every linked division with how each party and
 * each independent voted. Curator-only fields are never included.
 *
 * @phpstan-type Source array{label: string, url: ?string}
 * @phpstan-type PartySplit array{party: Party, ayes: int, noes: int, absent: int, position: DivisionPartyPosition}
 * @phpstan-type LinkedDivision array{link: PolicyDivision, parties: list<PartySplit>, independents: list<Vote>, ayes: list<string>, noes: list<string>}
 */
class PolicyEvidence
{
    /**
     * @return array{policy: Policy, sources: list<Source>, parties: list<array{party: Party, stance: SubjectStance}>, divisions: list<LinkedDivision>}
     */
    public function forPolicy(Policy $policy): array
    {
        $policy->load([
            'agreements' => fn ($query) => $query->where('subject_type', 'party'),
            'policyDivisions.division' => fn ($query) => $query->with([
                'house',
                'parliament',
                'proceedingsDocument',
                'partyPositions.party',
                'votes.member',
                'votes.party',
            ]),
        ]);

        return [
            'policy' => $policy,
            'sources' => self::sources($policy->sources),
            'parties' => $this->parties($policy),
            'divisions' => $policy->policyDivisions
                ->sortBy(fn (PolicyDivision $link): string => $link->division->sitting_date->format('Y-m-d').sprintf('-%s-%03d', $link->division->house->short_name, $link->division->sequence))
                ->map(fn (PolicyDivision $link): array => $this->division($link))
                ->values()
                ->all(),
        ];
    }

    /**
     * Splits the workbook's sources, one per line as "label: URL", into a
     * list. Only http and https addresses become links.
     *
     * @return list<Source>
     */
    public static function sources(?string $sources): array
    {
        return Str::of((string) $sources)
            ->explode("\n")
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->map(function (string $line): array {
                if (preg_match('~^(.*?):?\s*(https?://\S+)$~u', $line, $matches) === 1) {
                    return ['label' => rtrim(trim($matches[1]), ':') ?: $matches[2], 'url' => $matches[2]];
                }

                return ['label' => $line, 'url' => null];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{party: Party, stance: SubjectStance}>
     */
    private function parties(Policy $policy): array
    {
        $parties = Party::query()
            ->where('is_whipless', false)
            ->whereIn('id', $policy->agreements->pluck('subject_id'))
            ->get()
            ->sortBy(fn (Party $party): string => $party->display_name ?? $party->name);

        $rows = [];

        foreach ($parties as $party) {
            /** @var PolicyAgreement|null $record */
            $record = $policy->agreements->firstWhere('subject_id', $party->id);
            $stance = SubjectStance::for($policy, $party, $record);

            if ($stance !== null) {
                $rows[] = ['party' => $party, 'stance' => $stance];
            }
        }

        return $rows;
    }

    /**
     * @return LinkedDivision
     */
    private function division(PolicyDivision $link): array
    {
        $division = $link->division;
        $byName = fn (Vote $vote): string => $vote->member->last_name.' '.$vote->member->first_name;

        return [
            'link' => $link,
            'parties' => $division->partyPositions
                ->filter(fn (DivisionPartyPosition $position): bool => ! $position->party->is_whipless && $position->eligible > 0)
                ->sortBy(fn (DivisionPartyPosition $position): string => $position->party->display_name ?? $position->party->name)
                ->map(fn (DivisionPartyPosition $position): array => [
                    'party' => $position->party,
                    'ayes' => $position->ayes,
                    'noes' => $position->noes,
                    'absent' => max(0, $position->eligible - $position->ayes - $position->noes),
                    'position' => $position,
                ])
                ->values()
                ->all(),
            'independents' => $division->votes
                ->filter(fn (Vote $vote): bool => $vote->party === null || $vote->party->is_whipless)
                ->sortBy($byName)
                ->values()
                ->all(),
            'ayes' => $division->votes->where('vote', VoteValue::Aye)->sortBy($byName)->map(fn (Vote $vote): string => $vote->member->display_name)->values()->all(),
            'noes' => $division->votes->where('vote', VoteValue::No)->sortBy($byName)->map(fn (Vote $vote): string => $vote->member->display_name)->values()->all(),
        ];
    }
}
