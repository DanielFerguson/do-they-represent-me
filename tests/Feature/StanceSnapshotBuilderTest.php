<?php

use App\Domain\Stances\StanceSnapshotBuilder;
use App\Enums\AgreementCategory;
use App\Enums\PolicyStatus;
use App\Models\Division;
use App\Models\Electorate;
use App\Models\House;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Party;
use App\Models\Policy;
use App\Models\PolicyAgreement;
use App\Models\PolicyImport;

function buildStances(bool $includeReview = false): array
{
    return app(StanceSnapshotBuilder::class)->build($includeReview);
}

it('includes only published policies, in number order, with their public fields', function () {
    $second = Policy::factory()->published()->create(['number' => 12, 'verification_notes' => 'Private note', 'reviewer_notes' => 'Reviewer note']);
    $first = Policy::factory()->published()->create(['number' => 3, 'description' => 'What the bill did.']);
    Policy::factory()->create(['number' => 7, 'status' => PolicyStatus::Review]);
    Policy::factory()->create(['number' => 8, 'status' => PolicyStatus::Dropped]);

    $policies = buildStances()['policies'];

    expect(array_column($policies, 'id'))->toBe([3, 12])
        ->and(array_keys($policies[0]))->toBe(['id', 'slug', 'topic', 'title', 'question', 'description', 'agree_means', 'url', 'stances', 'members'])
        ->and($policies[0]['url'])->toBe("/policies/{$first->slug}")
        ->and($policies[0]['description'])->toBe('What the bill did.')
        ->and($policies[1]['question'])->toBe($second->question)
        ->and(json_encode($policies))->not->toContain('Private note')->not->toContain('Reviewer note');
});

it('adds policies still in review to previews', function () {
    Policy::factory()->published()->create(['number' => 1]);
    Policy::factory()->create(['number' => 2, 'status' => PolicyStatus::Review]);
    Policy::factory()->create(['number' => 3, 'status' => PolicyStatus::Dropped]);

    expect(array_column(buildStances(includeReview: true)['policies'], 'id'))->toBe([1, 2]);
});

it('lists parties with a record alphabetically, leaving out independents and parties with no record', function () {
    $policy = Policy::factory()->published()->create();
    $nationals = Party::factory()->create(['short_name' => 'NAT', 'display_name' => 'Nationals']);
    $greens = Party::factory()->create(['short_name' => 'GRN', 'display_name' => 'Greens']);
    $independents = Party::factory()->whipless()->create();
    Party::factory()->create(['display_name' => 'Aardvark Party']);
    PolicyAgreement::factory()->for($policy)->for($nationals, 'subject')->create();
    PolicyAgreement::factory()->for($policy)->for($greens, 'subject')->create();
    PolicyAgreement::factory()->for($policy)->for($independents, 'subject')->create();

    expect(buildStances()['parties'])->toBe([
        ['code' => 'GRN', 'short_name' => 'Greens', 'name' => $greens->name],
        ['code' => 'NAT', 'short_name' => 'Nationals', 'name' => $nationals->name],
    ]);
});

it('gives each party its agreement and the label for its band', function (float $agreement, string $label) {
    $policy = Policy::factory()->published()->create();
    $party = Party::factory()->create(['short_name' => 'ALP']);
    PolicyAgreement::factory()->for($policy)->for($party, 'subject')->agreement($agreement)->create();

    expect(buildStances()['policies'][0]['stances'])->toBe(['ALP' => ['agreement' => $agreement, 'label' => $label]]);
})->with([
    'consistently for' => [1.0, 'Consistently for'],
    'mixed at 40%' => [0.4, 'Mixed'],
    'generally against' => [0.1667, 'Generally against'],
]);

it('gives no figure where there are too few votes', function () {
    $policy = Policy::factory()->published()->create();
    $party = Party::factory()->create(['short_name' => 'ALP']);
    PolicyAgreement::factory()->for($policy)->for($party, 'subject')->unscored(AgreementCategory::NotEnough)->create();

    expect(buildStances()['policies'][0]['stances'])->toBe(['ALP' => ['agreement' => null, 'label' => 'Not enough votes']]);
});

it('shows a reviewers’ display note instead of the party’s figure, so the party is left out of matching', function () {
    $party = Party::factory()->create(['short_name' => 'LBT', 'display_name' => 'Libertarian']);
    $other = Party::factory()->create(['short_name' => 'ALP', 'display_name' => 'Labor']);
    $policy = Policy::factory()->published()->create(['display_notes' => [
        ['subject_type' => 'party', 'subject_id' => $party->id, 'note' => 'Voted for the bill but opposed the closure.'],
    ]]);
    PolicyAgreement::factory()->for($policy)->for($party, 'subject')->agreement(1.0)->create();
    PolicyAgreement::factory()->for($policy)->for($other, 'subject')->agreement(1.0)->create();

    expect(buildStances()['policies'][0]['stances'])->toBe([
        'ALP' => ['agreement' => 1.0, 'label' => 'Consistently for'],
        'LBT' => ['agreement' => null, 'label' => null, 'note' => 'Voted for the bill but opposed the closure.'],
    ]);
});

it('records how recent the data is and which workbook it came from', function () {
    Division::factory()->create(['sitting_date' => '2026-09-24']);
    Division::factory()->create(['sitting_date' => '2025-03-01']);
    PolicyImport::factory()->create();
    $latest = PolicyImport::factory()->create();

    $stances = buildStances();

    expect($stances['data_as_of'])->toBe('2026-09-24')
        ->and($stances['workbook_sha256'])->toBe($latest->sha256);
});

it('lists current MPs with their party, house and electorate, and the districts and regions, so results can show a voter\'s own MPs', function () {
    $assembly = House::factory()->create(['slug' => 'assembly']);
    $council = House::factory()->create(['slug' => 'council']);
    $region = Electorate::factory()->region()->create(['house_id' => $council->id, 'slug' => 'northern-metropolitan', 'name' => 'Northern Metropolitan']);
    $district = Electorate::factory()->inRegion($region)->create(['house_id' => $assembly->id, 'slug' => 'brunswick', 'name' => 'Brunswick']);
    $labor = Party::factory()->create(['display_name' => 'Labor']);
    $mla = Member::factory()->create(['slug' => 'jo-smith', 'display_name' => 'Jo Smith']);
    Membership::factory()->for($mla)->for($labor)->create(['house_id' => $assembly->id, 'electorate_id' => $district->id]);
    $former = Member::factory()->create();
    Membership::factory()->for($former)->between('2022-11-26', '2024-01-01')->create(['house_id' => $assembly->id, 'electorate_id' => $district->id]);

    $stances = buildStances();

    expect($stances['members'])->toBe([['slug' => 'jo-smith', 'name' => 'Jo Smith', 'party' => 'Labor', 'house' => 'assembly', 'electorate' => 'brunswick']])
        ->and($stances['districts'])->toBe([['slug' => 'brunswick', 'name' => 'Brunswick', 'region' => 'northern-metropolitan']])
        ->and($stances['regions'])->toBe([['slug' => 'northern-metropolitan', 'name' => 'Northern Metropolitan']]);
});

it('gives each current MP their own agreement, and a reviewers\' note instead of the figure where one applies', function () {
    $limbrick = Membership::factory()->for(Member::factory()->state(['display_name' => 'David Limbrick']))->create()->member;
    $other = Membership::factory()->for(Member::factory()->state(['display_name' => 'Zoe Other']))->create()->member;
    $policy = Policy::factory()->published()->create(['display_notes' => [
        ['subject_type' => 'member', 'subject_id' => $limbrick->id, 'note' => 'Voted for the bill but opposed the closure.'],
    ]]);
    PolicyAgreement::factory()->for($policy)->for($limbrick, 'subject')->agreement(1.0)->create();
    PolicyAgreement::factory()->for($policy)->for($other, 'subject')->agreement(0.0)->create();

    expect(buildStances()['policies'][0]['members'])->toBe([
        $limbrick->slug => ['agreement' => null, 'label' => null, 'note' => 'Voted for the bill but opposed the closure.'],
        $other->slug => ['agreement' => 0.0, 'label' => 'Consistently against'],
    ]);
});

it('leaves out MPs with no record on a policy, such as an MLA on a Council-only question', function () {
    Membership::factory()->create();
    Policy::factory()->published()->create();

    expect(buildStances()['policies'][0]['members'])->toBe([]);
});
