<?php

use App\Domain\Stances\StanceSnapshots;
use App\Enums\ElectorateKind;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Electorate;
use App\Models\Locality;
use App\Models\Policy;
use App\Models\PolicyAgreement;
use App\Models\PolicyDivision;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Everything the soft-launch checks need: 15 published questions with a
 * party figure each, live quiz data, the site settings and a district with
 * suburbs.
 */
function readyToLaunch(): Electorate
{
    config([
        'site.contact_email' => 'owner@example.com',
        'site.authorisation' => 'Authorised by A. Person, 1 Example Street, Melbourne.',
        'app.url' => 'https://dotheyrepresentme.com',
        'app.debug' => false,
    ]);

    foreach (range(1, 15) as $number) {
        $policy = Policy::factory()->published()->create(['number' => $number]);
        PolicyDivision::factory()->for($policy)->create();
        PolicyAgreement::factory()->for($policy)->create();
    }

    $district = Electorate::factory()->create(['kind' => ElectorateKind::District]);
    $district->localities()->attach(Locality::factory()->create(), ['share' => 1]);

    app(StanceSnapshots::class)->publish();

    return $district;
}

it('passes when the site is ready for the soft launch', function () {
    readyToLaunch();

    $this->artisan('vic:launch-check')
        ->expectsOutputToContain('Published questions')
        ->assertSuccessful();
});

it('fails, naming the problem, when a soft-launch condition is not met', function (Closure $break, string $problem) {
    readyToLaunch();
    $break();

    $this->artisan('vic:launch-check')
        ->expectsOutputToContain($problem)
        ->assertFailed();
})->with([
    'fewer than 15 published questions' => [fn () => Policy::query()->where('number', 15)->delete(), 'At least 15 published questions: 1'],
    'the data audit fails' => [fn () => Policy::factory()->published()->create(['number' => 99]), 'The data audit passes: 1'],
    'the live quiz data is out of date' => [fn () => Policy::query()->where('number', 1)->update(['question' => 'A changed question?']), 'The live quiz data is up to date: 1'],
    'no contact email' => [fn () => config(['site.contact_email' => null]), 'SITE_CONTACT_EMAIL is not set'],
    'no authorisation statement' => [fn () => config(['site.authorisation' => null]), 'SITE_AUTHORISATION is not set'],
    'an http address' => [fn () => config(['app.url' => 'http://dotheyrepresentme.com']), 'APP_URL is not https'],
    'debug mode in production' => [function () {
        config(['app.debug' => true]);
        app()->detectEnvironment(fn (): string => 'production');
    }, 'APP_DEBUG is on in production'],
    'a district with no suburbs' => [fn () => Electorate::factory()->create(['kind' => ElectorateKind::District, 'name' => 'Nowhere']), 'Nowhere'],
]);

it('also requires candidates in every electorate and frozen data at launch', function () {
    $district = readyToLaunch();

    $this->artisan('vic:launch-check', ['--launch' => true])
        ->expectsOutputToContain('Candidates for the next election in every district and region: 1')
        ->expectsOutputToContain('The scheduled sync is frozen: 1')
        ->assertFailed();

    Candidate::factory()->for(Election::factory())->for($district)->create();
    config(['services.parliament_vic.sync_frozen' => true]);

    $this->artisan('vic:launch-check', ['--launch' => true])->assertSuccessful();
});

it('skips the scheduled proceedings sync while the data is frozen', function (bool $frozen) {
    config(['services.parliament_vic.sync_frozen' => $frozen]);

    $sync = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'vic:sync-proceedings'));

    expect($sync->filtersPass(app()))->toBe(! $frozen);
})->with(['frozen' => true, 'not frozen' => false]);
