<?php

namespace App\Domain\Policies\Importing;

use App\Enums\PolicyStatus;
use App\Enums\VoteValue;
use App\Models\Division;
use App\Models\Policy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Loads the curated policies and their linked divisions from the workbook,
 * which is the only place policy text is edited.
 *
 * The whole workbook is checked first and nothing changes unless it is
 * valid. Each linked division's printed totals must match the Divisions tab
 * the curators worked from, so a link can never silently point at a
 * different vote. Policies are matched on their workbook number (P15 is
 * policy 15), which is also their public ID in quiz links.
 *
 * @phpstan-type PolicyRow array<string, mixed>
 * @phpstan-type LinkRow array{division_id: int, direction: VoteValue, is_strong: bool, rationale: ?string}
 */
class PolicyWorkbookImporter
{
    /**
     * @var list<string>
     */
    private array $errors = [];

    public function import(PolicyWorkbook $workbook): PolicyImportResult
    {
        $this->errors = [];

        $policies = $this->policies($workbook);
        $links = $this->links($workbook, $policies);

        if ($this->errors !== []) {
            throw new InvalidPolicyWorkbook($this->errors);
        }

        return DB::transaction(fn (): PolicyImportResult => $this->persist($policies, $links));
    }

    /**
     * @return array<int, PolicyRow>
     *
     * @phpstan-impure
     */
    private function policies(PolicyWorkbook $workbook): array
    {
        $policies = [];
        $slugs = [];

        foreach ($workbook->policies as $row => $cells) {
            $cells += array_fill_keys(PolicyWorkbook::REQUIRED_HEADINGS[PolicyWorkbook::POLICIES], '');
            $id = $cells['ID'];
            $title = $cells['Policy title'];
            $question = $cells['Question (neutral wording)'];

            if ($id === '' || ($title === '' && $question === '' && $cells['Status'] === '')) {
                continue;
            }

            $number = $this->policyNumber($id);
            $status = PolicyStatus::fromWorkbook($cells['Status']);

            if ($number === null) {
                $this->errors[] = "Policies row {$row}: the ID [{$id}] should look like P01.";

                continue;
            }

            if ($status === null) {
                $this->errors[] = "Policies row {$row}: unknown status [{$cells['Status']}]. Use Review, Ready or Dropped.";
            }

            if ($title === '' || ($question === '' && $status !== PolicyStatus::Dropped)) {
                $this->errors[] = "Policies row {$row}: {$id} needs a policy title and a question.";
            }

            if (isset($policies[$number])) {
                $this->errors[] = "Policies row {$row}: {$id} is listed more than once.";
            }

            $slug = Str::slug($title);

            if ($title !== '' && isset($slugs[$slug])) {
                $this->errors[] = "Policies row {$row}: {$id} has the same title as {$slugs[$slug]}.";
            }

            $slugs[$slug] = $id;
            $policies[$number] = [
                'slug' => $slug,
                'title' => $title,
                'question' => $question,
                'topic' => $this->nullable($cells['Topic']),
                'status' => $status,
                'agree_means' => $this->nullable($cells['"Agree" means']),
                'rationale' => $this->nullable($cells['Why this question']),
                'description' => $this->nullable($cells['Description']),
                'arguments_for' => $this->nullable($cells['Arguments for']),
                'arguments_against' => $this->nullable($cells['Arguments against']),
                'sources' => $this->nullable($cells['Sources']),
                'verification_notes' => $this->nullable($cells['To verify before publishing']),
                'reviewer_notes' => $this->nullable($cells['Reviewer notes']),
            ];
        }

        return $policies;
    }

    /**
     * @param  array<int, PolicyRow>  $policies
     * @return array<int, array<int, LinkRow>> links by policy number, then division ID
     *
     * @phpstan-impure
     */
    private function links(PolicyWorkbook $workbook, array $policies): array
    {
        $divisions = Division::query()->with(['house', 'parliament'])->get()->keyBy(fn (Division $division): string => $division->reference());
        $listed = collect($workbook->divisions)->keyBy('Division ID');
        $links = [];

        foreach ($workbook->policyVotes as $row => $cells) {
            $cells += array_fill_keys(PolicyWorkbook::REQUIRED_HEADINGS[PolicyWorkbook::POLICY_VOTES], '');
            $policyId = $cells['Policy ID'];

            if ($policyId === '') {
                continue;
            }

            $number = $this->policyNumber($policyId);
            $reference = $cells['Division ID'];
            $division = $divisions->get($reference);
            $direction = VoteValue::tryFrom(strtolower($cells['Agree when vote is']));
            $isStrong = match (strtolower($cells['Strong?'])) {
                'y', 'yes' => true,
                'n', 'no' => false,
                default => null,
            };

            if ($number === null || ! isset($policies[$number])) {
                $this->errors[] = "Policy votes row {$row}: [{$policyId}] is not on the Policies tab.";
            }

            if ($division === null) {
                $this->errors[] = "Policy votes row {$row}: no imported division has the ID [{$reference}].";
            } else {
                $this->checkAgainstDivisionsTab($row, $division, $listed);
            }

            if ($direction === null) {
                $this->errors[] = "Policy votes row {$row}: \"Agree when vote is\" must be Aye or No, not [{$cells['Agree when vote is']}].";
            }

            if ($isStrong === null) {
                $this->errors[] = "Policy votes row {$row}: \"Strong?\" must be Y or N, not [{$cells['Strong?']}].";
            }

            if ($number !== null && $division !== null && isset($links[$number][$division->id])) {
                $this->errors[] = "Policy votes row {$row}: {$policyId} links {$reference} more than once.";
            }

            if ($number === null || $division === null || $direction === null || $isStrong === null) {
                continue;
            }

            $links[$number][$division->id] = [
                'division_id' => $division->id,
                'direction' => $direction,
                'is_strong' => $isStrong,
                'rationale' => $this->nullable($cells['Rationale (public)']),
            ];
        }

        return $links;
    }

    /**
     * @param  Collection<string, array<string, string>>  $listed
     */
    private function checkAgainstDivisionsTab(int $row, Division $division, Collection $listed): void
    {
        $reference = $division->reference();
        $expected = $listed->get($reference);

        if ($expected === null) {
            $this->errors[] = "Policy votes row {$row}: {$reference} is not on the Divisions tab, so the link cannot be checked.";

            return;
        }

        $expected += ['Ayes' => '', 'Noes' => ''];

        if ((int) $expected['Ayes'] !== $division->ayes_count || (int) $expected['Noes'] !== $division->noes_count) {
            $this->errors[] = "Policy votes row {$row}: {$reference} is {$expected['Ayes']}–{$expected['Noes']} on the Divisions tab but {$division->ayes_count}–{$division->noes_count} in the imported proceedings. Check the division ID.";
        }
    }

    /**
     * @param  array<int, PolicyRow>  $policies
     * @param  array<int, array<int, LinkRow>>  $links
     */
    private function persist(array $policies, array $links): PolicyImportResult
    {
        $existing = Policy::withTrashed()->get()->keyBy('number');
        $counts = [];

        foreach ($policies as $number => $policy) {
            $model = $existing->get($number) ?? new Policy(['number' => $number]);
            $model->fill($policy);
            $model->published_at = $model->status === PolicyStatus::Published ? ($model->published_at ?? now()) : null;
            $model->deleted_at = null;
            $model->save();

            $policyLinks = $links[$number] ?? [];
            $model->policyDivisions()->whereNotIn('division_id', array_keys($policyLinks))->delete();

            foreach ($policyLinks as $divisionId => $link) {
                $model->policyDivisions()->updateOrCreate(['division_id' => $divisionId], $link);
            }

            $counts[$model->status->value] = ($counts[$model->status->value] ?? 0) + 1;
        }

        $removed = Policy::query()->whereNotIn('number', array_keys($policies))->get();
        $removed->each->delete();

        return new PolicyImportResult($counts, array_sum(array_map(count(...), $links)), $removed->count());
    }

    private function policyNumber(string $id): ?int
    {
        return preg_match('/^P(\d{1,4})$/i', $id, $matches) === 1 ? (int) $matches[1] : null;
    }

    private function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
