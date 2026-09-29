<?php

namespace App\Domain\VicParliament\Members;

use App\Models\House;
use App\Models\MemberAlias;
use App\Models\Membership;
use Carbon\CarbonInterface;

/**
 * Matches names as printed in the Votes and Proceedings ("First Last") to
 * the member who held a seat in that house on the date of the division.
 *
 * Lookups use each member's display name, "first last", and any curated
 * aliases (for example printing errors such as "Rickie-Lee Tyrrell").
 */
class MemberResolver
{
    /**
     * @var array<int, array<string, list<array{member_id: int, party_id: int, starts_on: string, ends_on: ?string}>>>
     */
    private array $index = [];

    public function __construct(private NameNormalizer $normalizer) {}

    public function resolve(string $printedName, House $house, CarbonInterface $date): ?ResolvedVoter
    {
        $day = $date->toDateString();

        foreach ($this->indexFor($house)[$this->normalizer->normalize($printedName)] ?? [] as $membership) {
            if ($membership['starts_on'] <= $day && ($membership['ends_on'] === null || $membership['ends_on'] >= $day)) {
                return new ResolvedVoter($membership['member_id'], $membership['party_id'], $printedName);
            }
        }

        return null;
    }

    /**
     * Resolve a list of printed names. A name that fails to resolve is tried
     * as two run-together names ("Georgie Purcell Samantha Ratnam"), which
     * happens occasionally when a separator is missing in the source.
     *
     * @param  list<string>  $printedNames
     * @return array{resolved: list<ResolvedVoter>, unresolved: list<string>}
     */
    public function resolveMany(array $printedNames, House $house, CarbonInterface $date): array
    {
        $resolved = [];
        $unresolved = [];

        foreach ($printedNames as $printedName) {
            $voter = $this->resolve($printedName, $house, $date);

            if ($voter !== null) {
                $resolved[] = $voter;

                continue;
            }

            $split = $this->resolveRunTogether($printedName, $house, $date);

            if ($split === null) {
                $unresolved[] = $printedName;
            } else {
                array_push($resolved, ...$split);
            }
        }

        return ['resolved' => $resolved, 'unresolved' => $unresolved];
    }

    /**
     * Forget cached lookups, e.g. after memberships or aliases change.
     */
    public function flush(): void
    {
        $this->index = [];
    }

    /**
     * @return list<ResolvedVoter>|null
     */
    private function resolveRunTogether(string $printedName, House $house, CarbonInterface $date): ?array
    {
        $words = explode(' ', trim($printedName));

        for ($split = 2; $split <= count($words) - 2; $split++) {
            $first = $this->resolve(implode(' ', array_slice($words, 0, $split)), $house, $date);
            $second = $this->resolve(implode(' ', array_slice($words, $split)), $house, $date);

            if ($first !== null && $second !== null) {
                return [$first, $second];
            }
        }

        return null;
    }

    /**
     * @return array<string, list<array{member_id: int, party_id: int, starts_on: string, ends_on: ?string}>>
     */
    private function indexFor(House $house): array
    {
        if (isset($this->index[$house->id])) {
            return $this->index[$house->id];
        }

        $aliases = MemberAlias::query()->get(['member_id', 'normalized_name'])->groupBy('member_id');
        $index = [];

        $memberships = Membership::query()
            ->with('member:id,first_name,last_name,display_name')
            ->where('house_id', $house->id)
            ->get();

        foreach ($memberships as $membership) {
            $entry = [
                'member_id' => $membership->member_id,
                'party_id' => $membership->party_id,
                'starts_on' => $membership->starts_on->toDateString(),
                'ends_on' => $membership->ends_on?->toDateString(),
            ];

            $keys = [
                $this->normalizer->normalize($membership->member->display_name),
                $this->normalizer->normalize($membership->member->first_name.' '.$membership->member->last_name),
                ...$aliases->get($membership->member_id, collect())->pluck('normalized_name')->all(),
            ];

            foreach (array_unique($keys) as $key) {
                $index[$key][] = $entry;
            }
        }

        return $this->index[$house->id] = $index;
    }
}
