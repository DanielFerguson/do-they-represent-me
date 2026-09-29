<?php

namespace App\Domain\Scoring;

use App\Enums\VoteValue;
use App\Models\DivisionPartyPosition;
use App\Models\Policy;
use App\Models\PolicyAgreement;
use App\Models\Vote;

/**
 * Scores how closely each party and each member voted with every policy.
 *
 * Parties are scored from their position in each linked division (a split
 * or no position counts as not voting). Members are scored from their own
 * votes, only on divisions held while they had a seat in that house; a
 * member with no such division gets no score at all, rather than a zero.
 */
class PolicyAgreementCalculator
{
    /**
     * Replace every policy's agreement scores. Run after the party positions
     * have been recalculated. Returns the rows written.
     */
    public function recalculate(): int
    {
        $seats = SeatHolders::load();
        $policies = Policy::query()->with('policyDivisions.division')->get();
        $divisionIds = $policies->flatMap(fn (Policy $policy) => $policy->policyDivisions)->pluck('division_id')->unique()->all();
        $votes = $this->votesByDivision($divisionIds);
        $positions = $this->positionsByDivision($divisionIds);
        $computedAt = now();
        $rows = [];

        foreach ($policies as $policy) {
            /** @var array<string, AgreementTally> $tallies keyed by morph alias and ID */
            $tallies = [];

            foreach ($policy->policyDivisions as $link) {
                $divisionVotes = $votes[$link->division_id] ?? [];

                foreach (array_keys($seats->eligibleFor($link->division, $divisionVotes)) as $memberId) {
                    ($tallies["member|{$memberId}"] ??= new AgreementTally)->record($divisionVotes[$memberId] ?? null, $link->direction, $link->is_strong);
                }

                foreach ($positions[$link->division_id] ?? [] as $partyId => $position) {
                    ($tallies["party|{$partyId}"] ??= new AgreementTally)->record($position, $link->direction, $link->is_strong);
                }
            }

            foreach ($tallies as $subject => $tally) {
                [$type, $id] = explode('|', $subject);
                $rows[] = [
                    'policy_id' => $policy->id,
                    'subject_type' => $type,
                    'subject_id' => (int) $id,
                    ...$tally->toRecord(),
                    'computed_at' => $computedAt,
                ];
            }
        }

        PolicyAgreement::query()->delete();

        foreach (array_chunk($rows, 1000) as $chunk) {
            PolicyAgreement::query()->insert($chunk);
        }

        return count($rows);
    }

    /**
     * @param  list<int>  $divisionIds
     * @return array<int, array<int, VoteValue>> votes keyed by division, then member
     */
    private function votesByDivision(array $divisionIds): array
    {
        $votes = [];

        foreach (Vote::query()->whereIn('division_id', $divisionIds)->toBase()->get(['division_id', 'member_id', 'vote']) as $vote) {
            $votes[$vote->division_id][$vote->member_id] = VoteValue::from($vote->vote);
        }

        return $votes;
    }

    /**
     * @param  list<int>  $divisionIds
     * @return array<int, array<int, ?VoteValue>> party positions keyed by division, then party
     */
    private function positionsByDivision(array $divisionIds): array
    {
        $positions = [];

        foreach (DivisionPartyPosition::query()->whereIn('division_id', $divisionIds)->get() as $position) {
            $positions[$position->division_id][$position->party_id] = $position->position->asVote();
        }

        return $positions;
    }
}
