<?php

use App\Domain\Policies\Importing\InvalidPolicyWorkbook;
use App\Domain\Policies\Importing\PolicyWorkbook;
use App\Domain\Policies\Importing\PolicyWorkbookImporter;
use App\Models\Division;
use App\Models\House;
use App\Models\Parliament;
use App\Models\Policy;

function importedDivision(): Division
{
    return Division::factory()
        ->for(Parliament::factory()->state(['number' => 60]))
        ->for(House::factory()->state(['short_name' => 'LA']))
        ->create(['sitting_number' => 92, 'sequence' => 3, 'ayes_count' => 54, 'noes_count' => 29]);
}

/**
 * A one-policy, one-link workbook, with rows keyed by sheet row number.
 *
 * @param  array<int, array<string, string>>  $policies
 * @param  array<int, array<string, string>>  $policyVotes
 * @param  array<int, array<string, string>>  $divisions
 */
function workbook(array $policies = [], array $policyVotes = [], ?array $divisions = null): PolicyWorkbook
{
    $policy = ['ID' => 'P01', 'Status' => 'Review', 'Policy title' => 'Test policy', 'Question (neutral wording)' => 'Should this pass?'];
    $link = ['Policy ID' => 'P01', 'Division ID' => 'LA-60-092-03', 'Agree when vote is' => 'Aye', 'Strong?' => 'Y'];

    return new PolicyWorkbook(
        $policies ?: [2 => $policy],
        $policyVotes ?: [2 => $link],
        $divisions ?? [2 => ['Division ID' => 'LA-60-092-03', 'Ayes' => '54', 'Noes' => '29']],
    );
}

function expectRejected(PolicyWorkbook $workbook, string $error): void
{
    expect(fn () => app(PolicyWorkbookImporter::class)->import($workbook))
        ->toThrow(fn (InvalidPolicyWorkbook $exception) => expect($exception->errors)->toContain($error));

    expect(Policy::query()->count())->toBe(0);
}

it('rejects an invalid policy row', function (array $overrides, string $error) {
    importedDivision();

    expectRejected(workbook(policies: [2 => [...workbook()->policies[2], ...$overrides]]), $error);
})->with([
    'malformed ID' => [['ID' => 'Policy 1'], 'Policies row 2: the ID [Policy 1] should look like P01.'],
    'unknown status' => [['Status' => 'Maybe'], 'Policies row 2: unknown status [Maybe]. Use Review, Ready or Dropped.'],
    'no question' => [['Question (neutral wording)' => ''], 'Policies row 2: P01 needs a policy title and a question.'],
]);

it('rejects an invalid link row', function (array $overrides, string $error) {
    importedDivision();

    expectRejected(workbook(policyVotes: [2 => [...workbook()->policyVotes[2], ...$overrides]]), $error);
})->with([
    'policy not on the Policies tab' => [['Policy ID' => 'P02'], 'Policy votes row 2: [P02] is not on the Policies tab.'],
    'division that was never imported' => [['Division ID' => 'LA-60-092-09'], 'Policy votes row 2: no imported division has the ID [LA-60-092-09].'],
    'direction other than Aye or No' => [['Agree when vote is' => 'Yes'], 'Policy votes row 2: "Agree when vote is" must be Aye or No, not [Yes].'],
    'strong flag other than Y or N' => [['Strong?' => 'Maybe'], 'Policy votes row 2: "Strong?" must be Y or N, not [Maybe].'],
]);

it('rejects a link to a division missing from the Divisions tab', function () {
    importedDivision();

    expectRejected(workbook(divisions: []), 'Policy votes row 2: LA-60-092-03 is not on the Divisions tab, so the link cannot be checked.');
});

it('rejects a policy listed twice', function () {
    importedDivision();
    $policy = workbook()->policies[2];

    expectRejected(workbook(policies: [2 => $policy, 3 => $policy]), 'Policies row 3: P01 is listed more than once.');
});

it('rejects a division linked twice to the same policy', function () {
    importedDivision();
    $link = workbook()->policyVotes[2];

    expectRejected(workbook(policyVotes: [2 => $link, 3 => $link]), 'Policy votes row 3: P01 links LA-60-092-03 more than once.');
});

it('skips placeholder rows with an ID but no content', function () {
    importedDivision();

    $result = app(PolicyWorkbookImporter::class)->import(workbook(policies: [...workbook()->policies, 3 => ['ID' => 'P02']]));

    expect($result->policiesByStatus)->toBe(['review' => 1])
        ->and(Policy::query()->pluck('number')->all())->toBe([1]);
});
