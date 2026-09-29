<?php

namespace App\Domain\Stances;

use App\Enums\AgreementCategory;
use App\Enums\PolicyStatus;
use App\Models\Division;
use App\Models\Party;
use App\Models\Policy;
use App\Models\PolicyAgreement;
use App\Models\PolicyImport;

/**
 * Builds the data the quiz and results pages score answers against: each
 * policy's question and each party's agreement with it.
 *
 * Only public fields are included; reviewer and verification notes stay in
 * the admin panel. Parties are listed alphabetically so their order carries
 * no meaning. Where the workbook gives a display note for a party, the note
 * replaces the figure, so that party is left out of matching on that policy.
 *
 * @phpstan-type Stance array{agreement: ?float, label: ?string, note?: string}
 * @phpstan-type StancePayload array{data_as_of: ?string, workbook_sha256: ?string, parties: list<array{code: string, short_name: string, name: string}>, policies: list<array{id: int, slug: string, topic: ?string, title: string, question: string, description: ?string, stances: array<string, Stance>}>}
 */
class StanceSnapshotBuilder
{
    /**
     * @return StancePayload
     */
    public function build(bool $includeReview = false): array
    {
        $statuses = $includeReview ? [PolicyStatus::Published, PolicyStatus::Review] : [PolicyStatus::Published];
        $policies = Policy::query()
            ->whereIn('status', $statuses)
            ->orderBy('number')
            ->with(['agreements' => fn ($query) => $query->where('subject_type', 'party')])
            ->get();

        $parties = Party::query()
            ->where('is_whipless', false)
            ->whereIn('id', $policies->flatMap(fn (Policy $policy) => $policy->agreements->pluck('subject_id'))->unique())
            ->get()
            ->sortBy(fn (Party $party): string => $party->display_name ?? $party->name)
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
            'policies' => $policies->map(fn (Policy $policy): array => [
                'id' => $policy->number,
                'slug' => $policy->slug,
                'topic' => $policy->topic,
                'title' => $policy->title,
                'question' => $policy->question,
                'description' => $policy->description,
                'stances' => $this->stances($policy, $parties->all()),
            ])->all(),
        ];
    }

    /**
     * @param  list<Party>  $parties
     * @return array<string, Stance>
     */
    private function stances(Policy $policy, array $parties): array
    {
        $agreements = $policy->agreements->keyBy('subject_id');
        $notes = collect($policy->display_notes ?? [])->where('subject_type', 'party')->pluck('note', 'subject_id');
        $stances = [];

        foreach ($parties as $party) {
            /** @var PolicyAgreement|null $agreement */
            $agreement = $agreements->get($party->id);

            if ($notes->has($party->id)) {
                $stances[$party->short_name] = ['agreement' => null, 'label' => null, 'note' => (string) $notes->get($party->id)];
            } elseif ($agreement !== null) {
                $stances[$party->short_name] = [
                    'agreement' => $agreement->agreement === null ? null : round((float) $agreement->agreement, 4),
                    'label' => AgreementCategory::from($agreement->category)->label(),
                ];
            }
        }

        return $stances;
    }
}
