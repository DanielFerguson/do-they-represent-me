<?php

namespace App\Domain\Stances;

use App\Enums\ElectorateKind;
use App\Enums\PolicyStatus;
use App\Models\Division;
use App\Models\Electorate;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Party;
use App\Models\Policy;
use App\Models\PolicyAgreement;
use App\Models\PolicyImport;
use Closure;
use Illuminate\Support\Collection;

/**
 * Builds the data the quiz and results pages score answers against: each
 * policy's question, each party's and each current MP's agreement with it,
 * and the districts and regions, so results can show a voter's own MPs.
 *
 * Only public fields are included; reviewer and verification notes stay in
 * the admin panel. Parties are listed alphabetically so their order carries
 * no meaning. Stances come from SubjectStance, so where the workbook gives a
 * display note for a party or MP, the note replaces the figure.
 *
 * @phpstan-import-type Stance from SubjectStance
 *
 * @phpstan-type StancePayload array{
 *     data_as_of: ?string,
 *     workbook_sha256: ?string,
 *     parties: list<array{code: string, short_name: string, name: string}>,
 *     regions: list<array{slug: string, name: string}>,
 *     districts: list<array{slug: string, name: string, region: ?string}>,
 *     members: list<array{slug: string, name: string, party: string, house: string, electorate: string}>,
 *     policies: list<array{id: int, slug: string, topic: ?string, title: string, question: string, description: ?string, url: string, stances: array<string, Stance>, members: array<string, Stance>}>,
 * }
 */
class StanceSnapshotBuilder
{
    /**
     * @param  (Closure(Policy): string)|null  $policyUrl  the link to each policy's evidence page; public pages by default
     * @return StancePayload
     */
    public function build(bool $includeReview = false, ?Closure $policyUrl = null): array
    {
        $policyUrl ??= fn (Policy $policy): string => route('policies.show', $policy->slug, absolute: false);
        $statuses = $includeReview ? [PolicyStatus::Published, PolicyStatus::Review] : [PolicyStatus::Published];
        $policies = Policy::query()
            ->whereIn('status', $statuses)
            ->orderBy('number')
            ->with('agreements')
            ->get();

        $parties = Party::query()
            ->where('is_whipless', false)
            ->whereIn('id', $policies->flatMap(fn (Policy $policy) => $policy->agreements->where('subject_type', 'party')->pluck('subject_id'))->unique())
            ->get()
            ->sortBy(fn (Party $party): string => $party->display_name ?? $party->name)
            ->values();

        $seats = Membership::query()
            ->current()
            ->with(['member', 'party', 'house', 'electorate'])
            ->get()
            ->sortBy(fn (Membership $seat): string => $seat->member->display_name)
            ->values();

        $latestDivision = Division::query()->max('sitting_date');

        return [
            'data_as_of' => $latestDivision === null ? null : substr((string) $latestDivision, 0, 10),
            'workbook_sha256' => PolicyImport::query()->latest('id')->value('sha256'),
            'parties' => $parties->map(fn (Party $party): array => [
                'code' => $party->short_name,
                'short_name' => $party->display_name ?? $party->name,
                'name' => $party->name,
            ])->all(),
            ...$this->electorates(),
            'members' => $seats->map(fn (Membership $seat): array => [
                'slug' => $seat->member->slug,
                'name' => $seat->member->display_name,
                'party' => $seat->party->display_name ?? $seat->party->name,
                'house' => $seat->house->slug,
                'electorate' => $seat->electorate->slug,
            ])->all(),
            'policies' => $policies->map(fn (Policy $policy): array => [
                'id' => $policy->number,
                'slug' => $policy->slug,
                'topic' => $policy->topic,
                'title' => $policy->title,
                'question' => $policy->question,
                'description' => $policy->description,
                'url' => $policyUrl($policy),
                'stances' => $this->stances($policy, $parties, fn (Party $party): string => $party->short_name),
                'members' => $this->stances($policy, $seats->map->member, fn (Member $member): string => $member->slug),
            ])->all(),
        ];
    }

    /**
     * @return array{regions: list<array{slug: string, name: string}>, districts: list<array{slug: string, name: string, region: ?string}>}
     */
    private function electorates(): array
    {
        $electorates = Electorate::query()->with('region')->orderBy('name')->get();

        return [
            'regions' => $electorates->where('kind', ElectorateKind::Region)->map(fn (Electorate $region): array => [
                'slug' => $region->slug,
                'name' => $region->name,
            ])->values()->all(),
            'districts' => $electorates->where('kind', ElectorateKind::District)->map(fn (Electorate $district): array => [
                'slug' => $district->slug,
                'name' => $district->name,
                'region' => $district->region?->slug,
            ])->values()->all(),
        ];
    }

    /**
     * @template TSubject of Party|Member
     *
     * @param  Collection<int, TSubject>  $subjects
     * @param  Closure(TSubject): string  $key
     * @return array<string, Stance>
     */
    private function stances(Policy $policy, Collection $subjects, Closure $key): array
    {
        $records = $policy->agreements->keyBy(fn (PolicyAgreement $agreement): string => "{$agreement->subject_type}:{$agreement->subject_id}");
        $stances = [];

        foreach ($subjects as $subject) {
            $stance = SubjectStance::for($policy, $subject, $records->get("{$subject->getMorphClass()}:{$subject->getKey()}"));

            if ($stance !== null) {
                $stances[$key($subject)] = $stance->toArray();
            }
        }

        return $stances;
    }
}
