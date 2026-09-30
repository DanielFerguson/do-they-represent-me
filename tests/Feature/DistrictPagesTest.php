<?php

use App\Enums\PolicyStatus;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Electorate;
use App\Models\House;
use App\Models\Locality;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Party;
use App\Models\Policy;
use App\Models\PolicyAgreement;

/**
 * A region with one district in it, for Assembly and Council seats.
 *
 * @return array{assembly: House, council: House, region: Electorate, district: Electorate}
 */
function districtInRegion(): array
{
    $assembly = House::factory()->create(['slug' => 'assembly']);
    $council = House::factory()->create(['slug' => 'council']);
    $region = Electorate::factory()->region()->create(['house_id' => $council->id, 'name' => 'Northern Metropolitan']);
    $district = Electorate::factory()->inRegion($region)->create(['house_id' => $assembly->id, 'name' => 'Brunswick', 'slug' => 'brunswick']);

    return compact('assembly', 'council', 'region', 'district');
}

function seat(Electorate $electorate, House $house, string $name, string $party = 'Labor', ?string $endsOn = null): Membership
{
    return Membership::factory()
        ->for(Member::factory()->state(['display_name' => $name, 'last_name' => last(explode(' ', $name))]))
        ->for(Party::factory()->state(['display_name' => $party]))
        ->between('2022-11-26', $endsOn)
        ->create(['house_id' => $house->id, 'electorate_id' => $electorate->id]);
}

it('lists every district by region', function () {
    ['district' => $district] = districtInRegion();

    $this->get(route('districts.index'))
        ->assertOk()
        ->assertSee('Northern Metropolitan Region')
        ->assertSee(route('districts.show', $district->slug));
});

it('shows the district’s MLA and its region’s MLCs, but not former members', function () {
    ['assembly' => $assembly, 'council' => $council, 'region' => $region, 'district' => $district] = districtInRegion();
    seat($district, $assembly, 'Jo Member', 'Labor');
    seat($region, $council, 'Sam Upper', 'Greens');
    seat($region, $council, 'Alex Former', 'Liberal', endsOn: '2024-01-01');

    $this->get(route('districts.show', $district->slug))
        ->assertOk()
        ->assertSee('Brunswick District')
        ->assertSeeInOrder(['Member of the Legislative Assembly', 'Jo Member', 'Labor'])
        ->assertSeeInOrder(['Members of the Legislative Council for Northern Metropolitan Region', 'Sam Upper', 'Greens'])
        ->assertDontSee('Alex Former');
});

it('says a seat is vacant and names the last member', function () {
    ['assembly' => $assembly, 'district' => $district] = districtInRegion();
    seat($district, $assembly, 'Tim Former', 'Greens', endsOn: '2026-09-19');

    $this->get(route('districts.show', $district->slug))
        ->assertSee('This seat is vacant. Tim Former (Greens) was the member until 19 September 2026.');
});

it('only has pages for districts', function () {
    ['region' => $region] = districtInRegion();

    $this->get(route('districts.show', $region->slug))->assertNotFound();
    $this->get(route('districts.show', 'nowhere'))->assertNotFound();
});

it('shows each MP’s record on the published questions only, with reviewers’ notes and missing records', function () {
    ['assembly' => $assembly, 'district' => $district] = districtInRegion();
    $member = seat($district, $assembly, 'Jo Member')->member;
    $scored = Policy::factory()->published()->create(['number' => 1, 'question' => 'Should the levy stay?']);
    $noted = Policy::factory()->published()->create(['number' => 2, 'question' => 'Should logging stay banned?', 'display_notes' => [
        ['subject_type' => 'member', 'subject_id' => $member->id, 'note' => 'Voted for the bill but opposed the closure.'],
    ]]);
    Policy::factory()->published()->create(['number' => 3, 'question' => 'A Council-only question?']);
    $review = Policy::factory()->create(['number' => 4, 'status' => PolicyStatus::Review, 'question' => 'A question in review?']);
    PolicyAgreement::factory()->for($scored)->for($member, 'subject')->agreement(1.0)->create();
    PolicyAgreement::factory()->for($noted)->for($member, 'subject')->agreement(0.0)->create();
    PolicyAgreement::factory()->for($review)->for($member, 'subject')->agreement(1.0)->create();

    $this->get(route('districts.show', $district->slug))
        ->assertSee('Voted on 2 of the 3 questions')
        ->assertSeeInOrder(['Should the levy stay?', 'Consistently for'])
        ->assertSeeInOrder(['Should logging stay banned?', 'Voted for the bill but opposed the closure.'])
        ->assertSeeInOrder(['A Council-only question?', 'No vote recorded'])
        ->assertDontSee('Consistently against')
        ->assertDontSee('A question in review?');
});

it('lists the suburbs in the district, marking those split with another district', function () {
    ['district' => $district] = districtInRegion();
    $other = Electorate::factory()->create();
    Locality::factory()->create(['name' => 'Brunswick East'])->electorates()->attach($district, ['share' => 1]);
    $coburg = Locality::factory()->create(['name' => 'Coburg']);
    $coburg->electorates()->attach($district, ['share' => 0.22]);
    $coburg->electorates()->attach($other, ['share' => 0.78]);

    $this->get(route('districts.show', $district->slug))
        ->assertSeeInOrder(['Suburbs and localities', 'Brunswick East,', 'Coburg (part)']);
});

it('lists candidates in ballot order for the upcoming election only', function () {
    ['region' => $region, 'district' => $district] = districtInRegion();
    $upcoming = Election::factory()->create(['name' => '2026 Victorian state election']);
    $past = Election::factory()->past()->create();
    Candidate::factory()->for($upcoming)->for($district)->create(['ballot_position' => 2, 'surname' => 'Second', 'given_names' => 'Bea', 'ballot_party' => 'Liberal']);
    Candidate::factory()->for($upcoming)->for($district)->create(['ballot_position' => 1, 'surname' => 'First', 'given_names' => 'Al']);
    Candidate::factory()->for($upcoming)->for($region)->create(['ballot_group' => 'AA', 'ballot_position' => 1, 'surname' => 'Late', 'given_names' => 'Eve']);
    Candidate::factory()->for($upcoming)->for($region)->create(['ballot_group' => 'B', 'ballot_position' => 1, 'surname' => 'Middle', 'given_names' => 'Fay']);
    Candidate::factory()->for($upcoming)->for($region)->create(['ballot_group' => 'A', 'ballot_position' => 1, 'surname' => 'Upper', 'given_names' => 'Cy', 'ballot_party' => 'Greens', 'member_id' => Member::factory()]);
    Candidate::factory()->for($past)->for($district)->create(['surname' => 'Oldcandidate', 'given_names' => 'Di']);

    $this->get(route('districts.show', $district->slug))
        ->assertSee('Candidates at the 2026 Victorian state election')
        ->assertSeeInOrder(['FIRST, Al', 'Independent', 'SECOND, Bea', 'Liberal'])
        ->assertSeeInOrder(['Northern Metropolitan Region candidates', 'Group A:', 'UPPER, Cy', 'Greens', 'MP in the 60th Parliament', 'Group B:', 'MIDDLE, Fay', 'Group AA:', 'LATE, Eve'])
        ->assertDontSee('OLDCANDIDATE');
});

it('shows no candidates section before the ballot draw', function () {
    ['district' => $district] = districtInRegion();
    Election::factory()->create();

    $this->get(route('districts.show', $district->slug))->assertDontSee('Candidates at the');
});
