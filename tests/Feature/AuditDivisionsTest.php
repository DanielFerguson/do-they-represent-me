<?php

use App\Enums\AgreementCategory;
use App\Models\Division;
use App\Models\Membership;
use App\Models\Party;
use App\Models\Policy;
use App\Models\PolicyAgreement;
use App\Models\PolicyDivision;
use App\Models\Vote;

/**
 * A division in the member's house with one Aye vote from them.
 */
function divisionVotedBy(Membership $membership, string $date = '2024-05-01'): Division
{
    $division = Division::factory()->create(['house_id' => $membership->house_id, 'sitting_date' => $date, 'ayes_count' => 1]);
    Vote::factory()->for($division)->by($membership)->aye()->create();

    return $division;
}

it('passes when every vote was cast by a member holding a seat that day', function () {
    divisionVotedBy(Membership::factory()->create());

    $this->artisan('vic:audit')->assertSuccessful();
});

it('fails when a vote was cast by a member who held no seat in that house that day', function () {
    divisionVotedBy(Membership::factory()->between('2022-11-26', '2024-01-01')->create(), '2024-05-01');

    $this->artisan('vic:audit')
        ->expectsOutputToContain('Votes by members who held no seat in that house that day: 1')
        ->expectsOutputToContain('More voters than members holding a seat: 1')
        ->assertFailed();
});

it('fails when a published question has no linked divisions', function () {
    Policy::factory()->published()->create(['number' => 3, 'title' => 'Short stays']);

    $this->artisan('vic:audit')
        ->expectsOutputToContain('Published questions with nothing to compare: 1')
        ->expectsOutputToContain('P03 Short stays: no linked divisions')
        ->assertFailed();
});

it('fails when no party has a figure on a published question, unless reviewers gave a note', function (bool $withNote, bool $passes) {
    $party = Party::factory()->create();
    $policy = Policy::factory()->published()->create(['display_notes' => $withNote ? [['subject_type' => 'party', 'subject_id' => $party->id, 'note' => 'A note.']] : null]);
    PolicyDivision::factory()->for($policy)->create();
    PolicyAgreement::factory()->for($policy)->for($party, 'subject')->unscored(AgreementCategory::NotEnough)->create();

    $result = $this->artisan('vic:audit');

    $passes ? $result->assertSuccessful() : $result->expectsOutputToContain('no party has a figure')->assertFailed();
})->with([
    'no figure and no note' => [false, false],
    'a reviewers’ note' => [true, true],
]);
