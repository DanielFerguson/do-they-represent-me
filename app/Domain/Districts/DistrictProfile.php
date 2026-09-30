<?php

namespace App\Domain\Districts;

use App\Domain\Stances\SubjectStance;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Electorate;
use App\Models\Locality;
use App\Models\Membership;
use App\Models\Policy;
use App\Models\PolicyAgreement;
use Illuminate\Database\Eloquent\Collection;

/**
 * Everything a district page shows: the district's MLA and its region's
 * MLCs in the 60th Parliament with their record on each published question,
 * the suburbs in the district and, once the ballot draw is done, the
 * candidates standing there.
 *
 * @phpstan-type Record array{seat: Membership, stances: list<array{policy: Policy, stance: ?SubjectStance}>, voted: int}
 */
class DistrictProfile
{
    /**
     * @return array{district: Electorate, region: ?Electorate, member: ?Record, vacancy: ?Membership, regionMembers: list<Record>, policyCount: int, localities: Collection<int, Locality>, election: ?Election, candidates: Collection<int, Candidate>, regionCandidates: Collection<int, Candidate>}
     */
    public function for(Electorate $district): array
    {
        $region = $district->region;
        $policies = Policy::query()->published()->orderBy('number')->get();

        $seats = Membership::query()
            ->current()
            ->whereIn('electorate_id', array_filter([$district->id, $region?->id]))
            ->with(['member.policyAgreements' => fn ($query) => $query->whereIn('policy_id', $policies->modelKeys()), 'party'])
            ->get()
            ->sortBy(fn (Membership $seat): string => $seat->member->last_name.' '.$seat->member->first_name);

        $member = $seats->firstWhere('electorate_id', $district->id);
        $election = Election::upcoming();
        $candidates = $election === null ? new Collection : $election->candidates()
            ->whereIn('electorate_id', array_filter([$district->id, $region?->id]))
            ->with(['party', 'member'])
            ->orderByRaw('length(ballot_group), ballot_group')
            ->orderBy('ballot_position')
            ->get();

        return [
            'district' => $district,
            'region' => $region,
            'member' => $member === null ? null : $this->record($member, $policies),
            'vacancy' => $member === null ? $this->previousMember($district) : null,
            'regionMembers' => $seats
                ->where('electorate_id', $region?->id)
                ->map(fn (Membership $seat): array => $this->record($seat, $policies))
                ->values()
                ->all(),
            'policyCount' => $policies->count(),
            'localities' => $district->localities()->orderBy('name')->get(),
            'election' => $election,
            'candidates' => $candidates->where('electorate_id', $district->id)->values(),
            'regionCandidates' => $candidates->where('electorate_id', $region?->id)->values(),
        ];
    }

    /**
     * @param  Collection<int, Policy>  $policies
     * @return Record
     */
    private function record(Membership $seat, Collection $policies): array
    {
        $agreements = $seat->member->policyAgreements->keyBy('policy_id');

        $stances = $policies->map(function (Policy $policy) use ($seat, $agreements): array {
            /** @var PolicyAgreement|null $agreement */
            $agreement = $agreements->get($policy->id);

            return ['policy' => $policy, 'stance' => SubjectStance::for($policy, $seat->member, $agreement)];
        })->all();

        return [
            'seat' => $seat,
            'stances' => $stances,
            'voted' => collect($stances)->filter(fn (array $row): bool => ($row['stance']?->votes()['voted'] ?? 0) > 0)->count(),
        ];
    }

    /**
     * The last member for a district with no current member.
     */
    private function previousMember(Electorate $district): ?Membership
    {
        return Membership::query()
            ->where('electorate_id', $district->id)
            ->whereNotNull('ends_on')
            ->with(['member', 'party'])
            ->latest('ends_on')
            ->first();
    }
}
