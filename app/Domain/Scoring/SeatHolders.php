<?php

namespace App\Domain\Scoring;

use App\Models\Division;
use App\Models\Membership;

/**
 * Who held a seat in each house on a given day, and for which party, from
 * the dated memberships. Loaded once per recalculation.
 */
final class SeatHolders
{
    /**
     * @param  array<int, list<array{member_id: int, party_id: int, starts_on: string, ends_on: ?string}>>  $membershipsByHouse
     */
    private function __construct(private array $membershipsByHouse) {}

    public static function load(): self
    {
        $byHouse = [];

        foreach (Membership::query()->get(['member_id', 'house_id', 'party_id', 'starts_on', 'ends_on']) as $membership) {
            $byHouse[$membership->house_id][] = [
                'member_id' => $membership->member_id,
                'party_id' => $membership->party_id,
                'starts_on' => $membership->starts_on->toDateString(),
                'ends_on' => $membership->ends_on?->toDateString(),
            ];
        }

        return new self($byHouse);
    }

    /**
     * The members who could have voted in a division, with the party each
     * belonged to that day. A presiding officer who did not vote is left out:
     * the chair of the Assembly does not vote, so is not absent.
     *
     * @param  array<int, mixed>  $votesByMember  the division's votes, keyed by member ID
     * @return array<int, int> party ID keyed by member ID
     */
    public function eligibleFor(Division $division, array $votesByMember): array
    {
        $date = $division->sitting_date->toDateString();
        $chair = $division->presiding_member_id;
        $eligible = [];

        foreach ($this->membershipsByHouse[$division->house_id] ?? [] as $membership) {
            if ($membership['starts_on'] <= $date && ($membership['ends_on'] === null || $membership['ends_on'] >= $date)) {
                $eligible[$membership['member_id']] = $membership['party_id'];
            }
        }

        if ($chair !== null && ! array_key_exists($chair, $votesByMember)) {
            unset($eligible[$chair]);
        }

        return $eligible;
    }
}
