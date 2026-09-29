<?php

namespace App\Domain\Scoring;

use App\Enums\PartyPosition;
use App\Enums\VoteValue;
use App\Models\Division;
use App\Models\DivisionPartyPosition;
use App\Models\Party;
use Illuminate\Support\Facades\DB;

/**
 * Works out how each party voted in every division, using each member's
 * party on the day of the vote. Whipless members (independents) are never
 * grouped into a bloc, and a free vote gives no party a position.
 */
class PartyPositionCalculator
{
    /**
     * Replace every division's party positions. Returns the rows written.
     */
    public function recalculate(): int
    {
        $seats = SeatHolders::load();
        $whipless = Party::query()->where('is_whipless', true)->pluck('id')->flip()->all();
        $votes = $this->votesByDivision();
        $rows = [];

        foreach (Division::query()->get(['id', 'house_id', 'sitting_date', 'presiding_member_id', 'is_free_vote']) as $division) {
            $divisionVotes = $votes[$division->id] ?? [];
            $counts = [];

            foreach ($seats->eligibleFor($division, $divisionVotes) as $memberId => $partyId) {
                $counts[$partyId] ??= ['ayes' => 0, 'noes' => 0, 'eligible' => 0];
                $counts[$partyId]['eligible']++;
            }

            foreach ($divisionVotes as $vote) {
                $counts[$vote['party_id']] ??= ['ayes' => 0, 'noes' => 0, 'eligible' => 0];
                $counts[$vote['party_id']][$vote['vote'] === VoteValue::Aye ? 'ayes' : 'noes']++;
            }

            foreach (array_diff_key($counts, $whipless) as $partyId => $count) {
                $rows[] = [
                    'division_id' => $division->id,
                    'party_id' => $partyId,
                    ...$count,
                    'position' => ($division->is_free_vote ? PartyPosition::None : PartyPosition::fromCounts($count['ayes'], $count['noes']))->value,
                ];
            }
        }

        DivisionPartyPosition::query()->delete();

        foreach (array_chunk($rows, 1000) as $chunk) {
            DivisionPartyPosition::query()->insert($chunk);
        }

        return count($rows);
    }

    /**
     * @return array<int, array<int, array{party_id: int, vote: VoteValue}>> votes keyed by division, then member
     */
    private function votesByDivision(): array
    {
        $votes = [];

        foreach (DB::table('votes')->whereNotNull('party_id')->get(['division_id', 'member_id', 'party_id', 'vote']) as $vote) {
            $votes[$vote->division_id][$vote->member_id] = ['party_id' => (int) $vote->party_id, 'vote' => VoteValue::from($vote->vote)];
        }

        return $votes;
    }
}
