<?php

use App\Domain\Localities\LocalityDirectory;
use App\Domain\Stances\StanceSnapshots;
use App\Models\Electorate;
use App\Models\Party;
use App\Models\Policy;
use App\Models\PolicyAgreement;
use Illuminate\Support\Facades\URL;

it('renders the public pages', function (string $route, string $text) {
    $this->get(route($route))
        ->assertOk()
        ->assertSee($text, escape: false)
        ->assertSee('Your answers stay in your browser');
})->with([
    'home' => ['home', 'How do Victoria\'s parties actually vote?'],
    'the quiz on the home page' => ['home', 'Loading questions'],
    'results' => ['results', 'Working out your results'],
    'districts' => ['districts.index', 'Find your district'],
    'questions' => ['policies.index', 'The questions'],
    'methodology' => ['methodology', 'How the questions were chosen'],
    'privacy' => ['privacy', 'The public pages set no cookies'],
    'about' => ['about', 'Corrections and right of reply'],
]);

it('sets no cookies on public pages, so they stay private and cacheable', function (Closure $path) {
    $this->get($path())->assertOk()->assertHeaderMissing('Set-Cookie');
})->with([
    'home' => fn () => route('home'),
    'results' => fn () => route('results'),
    'districts' => fn () => route('districts.index'),
    'a district' => fn () => route('districts.show', Electorate::factory()->create()->slug),
    'questions' => fn () => route('policies.index'),
    'a question' => fn () => route('policies.show', Policy::factory()->published()->create()->slug),
    'methodology' => fn () => route('methodology'),
    'privacy' => fn () => route('privacy'),
    'about' => fn () => route('about'),
    'localities' => fn () => app(LocalityDirectory::class)->url(),
    'preview quiz' => fn () => URL::temporarySignedRoute('preview.quiz', now()->addDay(), absolute: false),
]);

it('moves the old quiz address to the home page permanently', function () {
    $this->get('/quiz')->assertStatus(301)->assertRedirect(route('home'));
});

it('shows the authorisation statement in the footer only once it is configured', function () {
    $this->get(route('about'))->assertDontSee('Authorised by');

    config(['site.authorisation' => 'Authorised by A. Person, 1 Example Street, Melbourne.']);

    $this->get(route('home'))->assertSee('Authorised by A. Person, 1 Example Street, Melbourne.');
});

it('links to the contact form from the about and privacy pages', function (string $route) {
    $this->get(route($route))->assertSee('href="'.route('contact').'"', escape: false);
})->with(['about', 'privacy']);

it('shows the live data version on the methodology page only once a snapshot is published', function () {
    $this->get(route('methodology'))->assertDontSee('Snapshot');

    Policy::factory()->published()->create();
    $snapshot = app(StanceSnapshots::class)->publish();

    $this->get(route('methodology'))
        ->assertSee('Snapshot')
        ->assertSee(route('stances.show', $snapshot->hash), escape: false);
});

it('says plainly on the methodology page that the questions were drafted and given their final review by AI', function () {
    $this->get(route('methodology'))
        ->assertSee('drafted with AI')
        ->assertSee('A final round of AI reviewers');
});

it('answers how many published questions put each balanced party on the yes side', function () {
    [$levy, $logging] = Policy::factory()->published()->count(2)->create();
    $inReview = Policy::factory()->create();
    $labor = Party::factory()->create(['short_name' => 'ALP', 'display_name' => 'Labor']);
    $greens = Party::factory()->create(['short_name' => 'GRN', 'display_name' => 'Greens']);
    $crossbench = Party::factory()->create(['short_name' => 'LCV', 'display_name' => 'Legalise Cannabis']);
    PolicyAgreement::factory()->for($levy)->for($labor, 'subject')->agreement(1.0)->create();
    PolicyAgreement::factory()->for($logging)->for($labor, 'subject')->agreement(0.6)->create();
    PolicyAgreement::factory()->for($inReview)->for($labor, 'subject')->agreement(1.0)->create();
    PolicyAgreement::factory()->for($levy)->for($greens, 'subject')->agreement(0.59)->create();
    PolicyAgreement::factory()->for($logging)->for($greens, 'subject')->agreement(0.95)->create();
    PolicyAgreement::factory()->for($levy)->for($crossbench, 'subject')->agreement(1.0)->create();

    $response = $this->get(route('methodology'));

    $response
        ->assertSee('Why only 2 questions?')
        ->assertSee('Of the 2 questions, the “yes” side includes Greens 1 and Labor 2.')
        ->assertDontSee('Legalise Cannabis');
});

it('leaves the party figures out of the common questions until questions are published', function () {
    Policy::factory()->create();

    $response = $this->get(route('methodology'));

    $response
        ->assertSee('Why so few questions?')
        ->assertDontSee('side includes');
});

it('marks the prototype data as sample data', function (string $route) {
    $this->get(route($route))->assertSee('Prototype.');
})->with(['home', 'results']);

it('publishes sample stances with stable policy ids and a stance for every party', function () {
    $data = json_decode(file_get_contents(public_path('stances/sample.json')), true, flags: JSON_THROW_ON_ERROR);
    $partyCodes = array_column($data['parties'], 'code');
    $ids = array_column($data['policies'], 'id');

    expect($data['sample'])->toBeTrue()
        ->and($ids)->toBe(array_values(array_unique($ids)))
        ->and($ids)->each->toBeInt();

    foreach ($data['policies'] as $policy) {
        expect(array_keys($policy['stances']))->toBe($partyCodes);

        foreach ($policy['stances'] as $stance) {
            expect($stance['agreement'] === null || ($stance['agreement'] >= 0 && $stance['agreement'] <= 1))->toBeTrue();
        }
    }
});

it('points the quiz and results at the sample data while no policy is published', function (string $route) {
    $this->get(route($route))->assertSee('data-stances-url="'.asset('stances/sample.json').'"', escape: false);
})->with(['home', 'results']);

it('points the quiz and results at the published data once a policy is published', function (string $route) {
    Policy::factory()->published()->create();
    $snapshot = app(StanceSnapshots::class)->publish();

    $this->get(route($route))->assertSee('data-stances-url="'.route('stances.show', $snapshot->hash).'"', escape: false);
})->with(['home', 'results']);

it('drops the prototype notice once a policy is published', function (string $route) {
    Policy::factory()->published()->create();
    app(StanceSnapshots::class)->publish();

    $this->get(route($route))->assertDontSee('Prototype.');
})->with(['home', 'results']);

// A district page has its own picture, made for that district. See DistrictShareImageTest.
it('gives each section its own share image', function (Closure $path, string $image) {
    $this->get($path())
        ->assertOk()
        ->assertSee('<meta property="og:image" content="'.asset("images/{$image}").'">', escape: false);

    expect(public_path("images/{$image}"))->toBeFile();
})->with([
    'home' => [fn () => route('home'), 'share.png'],
    'questions' => [fn () => route('policies.index'), 'share-questions.png'],
    'a question' => [fn () => route('policies.show', Policy::factory()->published()->create()->slug), 'share-questions.png'],
    'districts' => [fn () => route('districts.index'), 'share-district.png'],
    'methodology' => [fn () => route('methodology'), 'share-methodology.png'],
    'about' => [fn () => route('about'), 'share-methodology.png'],
]);

it('shows a beta note instead of the prototype notice once real questions are published', function (string $route) {
    Policy::factory()->published()->create();
    app(StanceSnapshots::class)->publish();

    $this->get(route($route))
        ->assertSee('Beta.')
        ->assertSee(route('contact', ['topic' => 'correction']), escape: false)
        ->assertDontSee('Prototype.');
})->with(['home', 'results']);

it('keeps the prototype notice, not the beta note, while the quiz uses sample questions', function () {
    $this->get(route('home'))->assertSee('Prototype.')->assertDontSee('Beta.');
});

it('drops the beta note once the site is switched out of beta', function () {
    config(['site.beta' => false]);
    Policy::factory()->published()->create();
    app(StanceSnapshots::class)->publish();

    $this->get(route('home'))->assertDontSee('Beta.');
});
