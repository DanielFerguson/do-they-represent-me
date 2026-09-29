<?php

use App\Enums\PolicyStatus;
use App\Enums\VoteValue;
use App\Models\Division;
use App\Models\House;
use App\Models\Membership;
use App\Models\Parliament;
use App\Models\Policy;
use App\Models\PolicyDivision;
use App\Models\Vote;
use Illuminate\Support\Facades\Storage;

const WORKBOOK_PATH = 'policy-research/policy-workbook-v2.xlsx';

/**
 * The divisions the fixture workbook links to, with the totals its Divisions tab lists.
 *
 * @return array<string, Division>
 */
function fixtureWorkbookDivisions(): array
{
    $parliament = Parliament::factory()->create(['number' => 60]);
    $assembly = House::factory()->create(['short_name' => 'LA']);
    $council = House::factory()->create(['short_name' => 'LC']);

    return [
        'LA-60-092-02' => Division::factory()->for($parliament)->for($assembly)->create(['sitting_number' => 92, 'sequence' => 2, 'ayes_count' => 29, 'noes_count' => 54]),
        'LA-60-092-03' => Division::factory()->for($parliament)->for($assembly)->create(['sitting_number' => 92, 'sequence' => 3, 'ayes_count' => 54, 'noes_count' => 29]),
        'LC-60-025-01' => Division::factory()->for($parliament)->for($council)->create(['sitting_number' => 25, 'sequence' => 1, 'ayes_count' => 20, 'noes_count' => 15]),
    ];
}

function storeWorkbook(string $fixture = 'policy-workbook.xlsx'): void
{
    Storage::fake();
    Storage::put(WORKBOOK_PATH, (string) file_get_contents(base_path("tests/Fixtures/Workbooks/{$fixture}")));
}

it('imports each policy with its status, text and linked divisions', function () {
    $divisions = fixtureWorkbookDivisions();
    storeWorkbook();

    $this->artisan('vic:import-policies')->assertSuccessful();

    expect(Policy::query()->orderBy('number')->get()->map->only(['number', 'slug', 'status', 'question', 'topic', 'agree_means', 'description', 'arguments_for', 'arguments_against', 'sources', 'verification_notes', 'reviewer_notes'])->all())->toBe([
        ['number' => 1, 'slug' => 'first-test-policy', 'status' => PolicyStatus::Review, 'question' => 'Should the first test question pass?', 'topic' => 'Test topic', 'agree_means' => 'Agree = supporting the test bill', 'description' => 'A test description.', 'arguments_for' => 'Supporters said it tests well.', 'arguments_against' => 'Opponents said it is only a test.', 'sources' => 'https://example.test/source', 'verification_notes' => 'UNVERIFIED: nothing', 'reviewer_notes' => null],
        ['number' => 2, 'slug' => 'second-test-policy', 'status' => PolicyStatus::Published, 'question' => 'Should the second test question pass?', 'topic' => 'Other topic', 'agree_means' => 'Agree = supporting the motion', 'description' => 'Another description.', 'arguments_for' => 'For.', 'arguments_against' => 'Against.', 'sources' => null, 'verification_notes' => null, 'reviewer_notes' => 'Reviewer note'],
        ['number' => 3, 'slug' => 'dropped-test-policy', 'status' => PolicyStatus::Dropped, 'question' => '', 'topic' => 'Test topic', 'agree_means' => null, 'description' => null, 'arguments_for' => null, 'arguments_against' => null, 'sources' => null, 'verification_notes' => null, 'reviewer_notes' => null],
    ]);

    expect(Policy::query()->where('number', 2)->value('published_at'))->not->toBeNull()
        ->and(Policy::query()->where('number', 1)->value('published_at'))->toBeNull();

    expect(PolicyDivision::query()->orderBy('id')->get()->map->only(['division_id', 'direction', 'is_strong', 'rationale'])->all())->toBe([
        ['division_id' => $divisions['LA-60-092-02']->id, 'direction' => VoteValue::No, 'is_strong' => false, 'rationale' => 'Reasoned amendment (Assembly).'],
        ['division_id' => $divisions['LA-60-092-03']->id, 'direction' => VoteValue::No, 'is_strong' => true, 'rationale' => 'Second reading (Assembly).'],
        ['division_id' => $divisions['LC-60-025-01']->id, 'direction' => VoteValue::Aye, 'is_strong' => false, 'rationale' => 'Committee vote (Council).'],
    ]);
});

it('recalculates scores after importing', function () {
    $divisions = fixtureWorkbookDivisions();
    $seat = Membership::factory()->for($divisions['LA-60-092-03']->house)->create();
    Vote::factory()->for($divisions['LA-60-092-03'])->by($seat)->no()->create();
    storeWorkbook();

    $this->artisan('vic:import-policies')->assertSuccessful();

    $this->assertDatabaseHas('policy_agreements', [
        'policy_id' => Policy::query()->where('number', 1)->value('id'),
        'subject_type' => 'member',
        'subject_id' => $seat->member_id,
        'votes_same_strong' => 1,
        'votes_absent' => 1,
        'category' => 'for3',
    ]);
});

it('updates imported policies in place and removes links and policies no longer in the workbook', function () {
    $divisions = fixtureWorkbookDivisions();
    $existing = Policy::factory()->create(['number' => 1, 'title' => 'Old title']);
    $staleLink = PolicyDivision::factory()->for($existing)->create();
    $removed = Policy::factory()->create(['number' => 9]);
    storeWorkbook();

    $this->artisan('vic:import-policies')->assertSuccessful();

    expect($existing->fresh()->title)->toBe('First test policy')
        ->and($existing->policyDivisions()->pluck('division_id')->all())->toEqualCanonicalizing([$divisions['LA-60-092-02']->id, $divisions['LA-60-092-03']->id]);
    $this->assertModelMissing($staleLink);
    $this->assertSoftDeleted($removed);
});

it('imports nothing when a linked division does not match the Divisions tab', function () {
    fixtureWorkbookDivisions()['LA-60-092-03']->update(['ayes_count' => 53]);
    storeWorkbook();

    $this->artisan('vic:import-policies')
        ->expectsOutputToContain('Policy votes row 3: LA-60-092-03 is 54–29 on the Divisions tab but 53–29 in the imported proceedings. Check the division ID.')
        ->assertFailed();

    expect(Policy::query()->count())->toBe(0);
});

it('reports columns missing from the workbook', function () {
    fixtureWorkbookDivisions();
    storeWorkbook('policy-workbook-missing-status-column.xlsx');

    $this->artisan('vic:import-policies')
        ->expectsOutputToContain('Policies tab: missing the column [Status].')
        ->assertFailed();

    expect(Policy::query()->count())->toBe(0);
});

it('fails when there is no workbook at the path', function () {
    Storage::fake();

    $this->artisan('vic:import-policies', ['path' => 'missing.xlsx'])
        ->expectsOutput('No workbook at [missing.xlsx] on the default disk.')
        ->assertFailed();
});
