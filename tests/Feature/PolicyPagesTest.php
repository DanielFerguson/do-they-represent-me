<?php

use App\Enums\PartyPosition;
use App\Enums\PolicyStatus;
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
use App\Models\ProceedingsDocument;
use App\Models\Vote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

it('lists the published questions by topic', function () {
    Policy::factory()->published()->create(['topic' => 'Housing', 'question' => 'Should short stays be taxed?']);
    Policy::factory()->create(['status' => PolicyStatus::Review, 'question' => 'A question still in review?']);
    Policy::factory()->create(['status' => PolicyStatus::Dropped, 'question' => 'A dropped question?']);

    $this->get(route('policies.index'))
        ->assertOk()
        ->assertSee('Housing')
        ->assertSee('Should short stays be taxed?')
        ->assertDontSee('A question still in review?')
        ->assertDontSee('A dropped question?');
});

it('says the questions are under review while none are published', function () {
    $this->get(route('policies.index'))->assertSee('being checked by reviewers');
});

it('shows only published questions publicly', function (PolicyStatus $status) {
    $policy = Policy::factory()->create(['status' => $status]);

    $this->get(route('policies.show', $policy->slug))->assertNotFound();
})->with([PolicyStatus::Review, PolicyStatus::Draft, PolicyStatus::Dropped]);

it('shows the evidence behind a question: the text, each linked vote and how every party voted', function () {
    $house = House::factory()->create(['name' => 'Legislative Assembly']);
    $document = ProceedingsDocument::factory()->for($house)->create(['title' => 'Votes and Proceedings No 90', 'pdf_url' => '/globalassets/2024vp090.pdf']);
    $division = Division::factory()->for($house)->for($document)->create([
        'sitting_date' => '2024-09-11',
        'item_title' => 'SHORT STAY LEVY BILL 2024',
        'question' => 'That this Bill be now read a second time.',
        'result' => 'Question agreed to.',
        'ayes_count' => 2,
        'noes_count' => 1,
    ]);
    $labor = Party::factory()->create(['display_name' => 'Labor']);
    $independents = Party::factory()->whipless()->create();
    $policy = Policy::factory()->published()->create([
        'question' => 'Should Victoria abolish the short-stay levy?',
        'description' => 'The bill created a levy on short stays.',
        'arguments_for' => 'Opponents said it would hurt tourism.',
        'arguments_against' => 'Supporters said it would free up rentals.',
        'verification_notes' => 'Private verification note',
        'reviewer_notes' => 'Private reviewer note',
        'rationale' => 'Private curator rationale',
    ]);
    PolicyDivision::factory()->for($policy)->for($division)->agreeWhen(VoteValue::No)->strong()->create(['rationale' => 'The vote that passed the levy.']);
    DivisionPartyPosition::query()->create(['division_id' => $division->id, 'party_id' => $labor->id, 'ayes' => 2, 'noes' => 0, 'eligible' => 3, 'position' => PartyPosition::Aye]);
    $independent = Membership::factory()->for($independents)->for(Member::factory()->state(['display_name' => 'Pat Independent']))->create(['house_id' => $house->id]);
    Vote::factory()->for($division)->by($independent)->no()->create();
    PolicyAgreement::factory()->for($policy)->for($labor, 'subject')->agreement(0.0)->create(['votes_same_strong' => 0, 'votes_differ_strong' => 1, 'votes_absent' => 1]);

    $this->get(route('policies.show', $policy->slug))
        ->assertOk()
        ->assertSee('Should Victoria abolish the short-stay levy?')
        ->assertSee('The bill created a levy on short stays.')
        ->assertSee('Opponents said it would hurt tourism.')
        ->assertSee('Supporters said it would free up rentals.')
        ->assertSeeInOrder(['Labor', 'Consistently against', '1 of 2'])
        ->assertSee('11 September 2024')
        ->assertSee('Short Stay Levy Bill 2024')
        ->assertSee('That this Bill be now read a second time.')
        ->assertSee('A No vote')
        ->assertSee('Strong')
        ->assertSee('The vote that passed the levy.')
        ->assertSeeInOrder(['Labor', '2', '0', '1', 'Aye'])
        ->assertSee('Pat Independent (No)')
        ->assertSee('href="https://www.parliament.vic.gov.au/globalassets/2024vp090.pdf"', escape: false)
        ->assertSee(e(route('contact', ['topic' => 'correction', 'policy' => $policy->slug])), escape: false)
        ->assertDontSee('Private verification note')
        ->assertDontSee('Private reviewer note')
        ->assertDontSee('Private curator rationale');
});

it('shows a reviewers’ note instead of a party’s figure', function () {
    $party = Party::factory()->create(['display_name' => 'Libertarian']);
    $policy = Policy::factory()->published()->create(['display_notes' => [
        ['subject_type' => 'party', 'subject_id' => $party->id, 'note' => 'Voted for the bill but opposed the closure.'],
    ]]);
    PolicyAgreement::factory()->for($policy)->for($party, 'subject')->agreement(0.0)->create();

    $this->get(route('policies.show', $policy->slug))
        ->assertSee('Voted for the bill but opposed the closure.')
        ->assertDontSee('Consistently against');
});

it('links only web addresses in the sources, and escapes everything', function () {
    $policy = Policy::factory()->published()->create(['sources' => implode("\n", [
        'EM: https://content.legislation.vic.gov.au/em.docx',
        'Bad: javascript:alert(1)',
        'Second reading <script>alert(1)</script>',
    ])]);

    $this->get(route('policies.show', $policy->slug))
        ->assertSee('<a href="https://content.legislation.vic.gov.au/em.docx" class="link" rel="noopener">EM</a>', escape: false)
        ->assertDontSee('href="javascript:', escape: false)
        ->assertSee('Bad: javascript:alert(1)')
        ->assertDontSee('<script>alert(1)</script>', escape: false);
});

it('shows reviewers the evidence for a question still in review through a signed link only', function () {
    $policy = Policy::factory()->create(['status' => PolicyStatus::Review, 'question' => 'A question in review?']);

    $this->get("/preview/policies/{$policy->slug}")->assertForbidden();

    $this->get(URL::temporarySignedRoute('preview.policies.show', now()->addDay(), ['policy' => $policy->slug], absolute: false))
        ->assertOk()
        ->assertSee('A question in review?')
        ->assertSee('Preview.')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('never previews a dropped question', function () {
    $policy = Policy::factory()->create(['status' => PolicyStatus::Dropped]);

    $this->get(URL::temporarySignedRoute('preview.policies.show', now()->addDay(), ['policy' => $policy->slug], absolute: false))->assertNotFound();
});

it('links each question in the preview data to its signed preview evidence page, with the same expiry', function () {
    $policy = Policy::factory()->create(['status' => PolicyStatus::Review]);
    $expires = now()->addDays(3);

    $url = $this->get(URL::temporarySignedRoute('preview.stances', $expires, absolute: false))->json('policies.0.url');

    expect($url)->toStartWith("/preview/policies/{$policy->slug}?expires={$expires->getTimestamp()}");
    $this->get($url)->assertOk();
});

it('shows a question’s evidence in a fixed number of queries, however many votes are linked', function (int $divisions) {
    $policy = Policy::factory()->published()->create();
    $party = Party::factory()->create();
    PolicyAgreement::factory()->for($policy)->for($party, 'subject')->create();

    foreach (range(1, $divisions) as $index) {
        $membership = Membership::factory()->for($party)->create();
        $division = Division::factory()->create(['house_id' => $membership->house_id, 'ayes_count' => 1]);
        PolicyDivision::factory()->for($policy)->for($division)->create();
        DivisionPartyPosition::query()->create(['division_id' => $division->id, 'party_id' => $party->id, 'ayes' => 1, 'noes' => 0, 'eligible' => 1, 'position' => PartyPosition::Aye]);
        Vote::factory()->for($division)->by($membership)->aye()->create();
    }

    DB::enableQueryLog();
    $this->get(route('policies.show', $policy->slug))->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(15);
})->with([1, 8]);
