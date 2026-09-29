<?php

use App\Enums\VoteValue;
use App\Models\Division;
use App\Models\DivisionPartyPosition;
use App\Models\House;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Party;
use App\Models\Policy;
use App\Models\PolicyAgreement;
use App\Models\PolicyDivision;
use App\Models\StanceSnapshot;
use App\Models\Vote;

/**
 * @return list<array<string, mixed>>
 */
function partyPositions(Division $division): array
{
    return DivisionPartyPosition::query()->whereBelongsTo($division)->orderBy('party_id')
        ->get(['party_id', 'ayes', 'noes', 'eligible', 'position'])->toArray();
}

it('records each party’s majority position and how many of its members could vote', function () {
    $house = House::factory()->create();
    [$red, $blue, $green] = Party::factory()->count(3)->create();
    $reds = Membership::factory()->count(3)->for($house)->for($red)->create();
    $blues = Membership::factory()->count(2)->for($house)->for($blue)->create();
    Membership::factory()->for($house)->for($green)->create();
    $division = Division::factory()->for($house)->create();
    Vote::factory()->for($division)->by($reds[0])->aye()->create();
    Vote::factory()->for($division)->by($reds[1])->aye()->create();
    Vote::factory()->for($division)->by($reds[2])->no()->create();
    Vote::factory()->for($division)->by($blues[0])->aye()->create();
    Vote::factory()->for($division)->by($blues[1])->no()->create();

    $this->artisan('vic:score')->assertSuccessful();

    expect(partyPositions($division))->toBe([
        ['party_id' => $red->id, 'ayes' => 2, 'noes' => 1, 'eligible' => 3, 'position' => 'aye'],
        ['party_id' => $blue->id, 'ayes' => 1, 'noes' => 1, 'eligible' => 2, 'position' => 'split'],
        ['party_id' => $green->id, 'ayes' => 0, 'noes' => 0, 'eligible' => 1, 'position' => 'none'],
    ]);
});

it('counts a member under the party they belonged to on the day of the vote', function () {
    $house = House::factory()->create();
    [$red, $blue] = Party::factory()->count(2)->create();
    $switcher = Member::factory()->create();
    Membership::factory()->for($house)->for($red)->for($switcher)->between('2022-11-26', '2024-03-31')->create();
    $switcherNow = Membership::factory()->for($house)->for($blue)->for($switcher)->between('2024-04-01', null)->create();
    $loyal = Membership::factory()->for($house)->for($red)->create();
    $division = Division::factory()->for($house)->create(['sitting_date' => '2024-05-01']);
    Vote::factory()->for($division)->by($switcherNow)->no()->create();
    Vote::factory()->for($division)->by($loyal)->aye()->create();

    $this->artisan('vic:score')->assertSuccessful();

    expect(partyPositions($division))->toBe([
        ['party_id' => $red->id, 'ayes' => 1, 'noes' => 0, 'eligible' => 1, 'position' => 'aye'],
        ['party_id' => $blue->id, 'ayes' => 0, 'noes' => 1, 'eligible' => 1, 'position' => 'no'],
    ]);
});

it('gives no party a position on a free vote', function () {
    $house = House::factory()->create();
    $seat = Membership::factory()->for($house)->create();
    $division = Division::factory()->for($house)->freeVote()->create();
    Vote::factory()->for($division)->by($seat)->aye()->create();

    $this->artisan('vic:score')->assertSuccessful();

    expect(partyPositions($division))->toBe([
        ['party_id' => $seat->party_id, 'ayes' => 1, 'noes' => 0, 'eligible' => 1, 'position' => 'none'],
    ]);
});

it('scores independents one by one, never as a bloc', function () {
    $house = House::factory()->create();
    $independents = Party::factory()->whipless()->create();
    $seats = Membership::factory()->count(2)->for($house)->for($independents)->create();
    $link = PolicyDivision::factory()->for(Division::factory()->for($house))->create();
    Vote::factory()->for($link->division)->by($seats[0])->aye()->create();
    Vote::factory()->for($link->division)->by($seats[1])->aye()->create();

    $this->artisan('vic:score')->assertSuccessful();

    expect(partyPositions($link->division))->toBe([])
        ->and(PolicyAgreement::query()->where('subject_type', 'member')->pluck('subject_id')->all())->toEqualCanonicalizing($seats->pluck('member_id')->all())
        ->and(PolicyAgreement::query()->where('subject_type', 'party')->exists())->toBeFalse();
});

it('does not count a presiding officer who did not vote as eligible or absent', function () {
    $house = House::factory()->create();
    $party = Party::factory()->create();
    $chair = Membership::factory()->for($house)->for($party)->create();
    $member = Membership::factory()->for($house)->for($party)->create();
    $link = PolicyDivision::factory()->for(Division::factory()->for($house)->presidedBy($chair))->create();
    Vote::factory()->for($link->division)->by($member)->aye()->create();

    $this->artisan('vic:score')->assertSuccessful();

    expect(partyPositions($link->division)[0]['eligible'])->toBe(1);
    $this->assertDatabaseMissing('policy_agreements', ['subject_type' => 'member', 'subject_id' => $chair->member_id]);
});

it('scores each party from its positions, with strong votes weighted five times normal votes', function () {
    $house = House::factory()->create();
    [$red, $blue] = Party::factory()->count(2)->create();
    $redSeat = Membership::factory()->for($house)->for($red)->create();
    $blueSeat = Membership::factory()->for($house)->for($blue)->create();
    $policy = Policy::factory()->create();
    $strong = PolicyDivision::factory()->for($policy)->for(Division::factory()->for($house))->strong()->agreeWhen(VoteValue::Aye)->create();
    $normal = PolicyDivision::factory()->for($policy)->for(Division::factory()->for($house))->agreeWhen(VoteValue::Aye)->create();
    Vote::factory()->for($strong->division)->by($redSeat)->aye()->create();
    Vote::factory()->for($strong->division)->by($blueSeat)->no()->create();
    Vote::factory()->for($normal->division)->by($redSeat)->no()->create();
    Vote::factory()->for($normal->division)->by($blueSeat)->aye()->create();

    $this->artisan('vic:score')->assertSuccessful();

    $this->assertDatabaseHas('policy_agreements', ['policy_id' => $policy->id, 'subject_type' => 'party', 'subject_id' => $red->id, 'votes_same_strong' => 1, 'votes_differ' => 1, 'agreement' => 0.8333, 'category' => 'for1']);
    $this->assertDatabaseHas('policy_agreements', ['policy_id' => $policy->id, 'subject_type' => 'party', 'subject_id' => $blue->id, 'votes_differ_strong' => 1, 'votes_same' => 1, 'agreement' => 0.1667, 'category' => 'against1']);
});

it('treats a party that split as not taking a position', function () {
    $house = House::factory()->create();
    $party = Party::factory()->create();
    $seats = Membership::factory()->count(2)->for($house)->for($party)->create();
    $link = PolicyDivision::factory()->for(Division::factory()->for($house))->strong()->create();
    Vote::factory()->for($link->division)->by($seats[0])->aye()->create();
    Vote::factory()->for($link->division)->by($seats[1])->no()->create();

    $this->artisan('vic:score')->assertSuccessful();

    $this->assertDatabaseHas('policy_agreements', ['subject_type' => 'party', 'subject_id' => $party->id, 'votes_absent_strong' => 1, 'agreement' => null, 'category' => 'did_not_vote']);
});

it('scores members only on divisions held while they had a seat in that house', function () {
    $assembly = House::factory()->create();
    $council = House::factory()->create();
    $present = Membership::factory()->for($assembly)->create();
    $sometimesAbsent = Membership::factory()->for($assembly)->create();
    $retired = Membership::factory()->for($assembly)->between('2022-11-26', '2024-01-31')->create();
    $councillor = Membership::factory()->for($council)->create();
    $policy = Policy::factory()->create();
    $early = PolicyDivision::factory()->for($policy)->for(Division::factory()->for($assembly)->state(['sitting_date' => '2023-05-01']))->strong()->create();
    $late = PolicyDivision::factory()->for($policy)->for(Division::factory()->for($assembly)->state(['sitting_date' => '2024-05-01']))->create();
    Vote::factory()->for($early->division)->by($present)->aye()->create();
    Vote::factory()->for($late->division)->by($present)->aye()->create();
    Vote::factory()->for($early->division)->by($sometimesAbsent)->aye()->create();
    Vote::factory()->for($early->division)->by($retired)->no()->create();

    $this->artisan('vic:score')->assertSuccessful();

    expect(PolicyAgreement::query()->where('subject_type', 'member')->get()->mapWithKeys(fn (PolicyAgreement $agreement) => [
        $agreement->subject_id => [$agreement->votes_same_strong, $agreement->votes_same, $agreement->votes_differ_strong, $agreement->votes_absent, $agreement->category],
    ])->all())->toEqual([
        $present->member_id => [1, 1, 0, 0, 'for3'],
        $sometimesAbsent->member_id => [1, 0, 0, 1, 'for3'],
        $retired->member_id => [0, 0, 1, 0, 'against3'],
    ])
        ->not->toHaveKey($councillor->member_id);
});

it('drops scores for policies that have been removed', function () {
    $house = House::factory()->create();
    $seat = Membership::factory()->for($house)->create();
    $link = PolicyDivision::factory()->for(Division::factory()->for($house))->strong()->create();
    Vote::factory()->for($link->division)->by($seat)->aye()->create();
    $this->artisan('vic:score')->assertSuccessful();
    $link->policy->delete();

    $this->artisan('vic:score')->assertSuccessful();

    expect(PolicyAgreement::query()->count())->toBe(0);
});

it('publishes the recalculated scores as the live quiz data', function () {
    $house = House::factory()->create();
    $party = Party::factory()->create(['short_name' => 'RED']);
    $seat = Membership::factory()->for($house)->for($party)->create();
    $link = PolicyDivision::factory()->for(Policy::factory()->published()->state(['number' => 7]))->for(Division::factory()->for($house))->strong()->create();
    Vote::factory()->for($link->division)->by($seat)->aye()->create();

    $this->artisan('vic:score')->expectsOutputToContain('Published quiz data version')->assertSuccessful();

    expect(json_decode(StanceSnapshot::query()->sole()->payload, true)['policies'][0])
        ->id->toBe(7)
        ->stances->toBe(['RED' => ['agreement' => 1.0, 'label' => 'Consistently for']]);
});
