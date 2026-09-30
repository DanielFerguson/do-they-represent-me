<?php

use App\Models\Electorate;
use App\Models\Policy;
use Illuminate\Support\Facades\URL;

it('marks the pages that count visits with the project key and the page type', function (Closure $path, string $pageType) {
    config(['services.posthog.key' => 'phc_test']);

    $this->get($path())
        ->assertOk()
        ->assertSee('data-posthog-key="phc_test"', escape: false)
        ->assertSee('data-page-type="'.$pageType.'"', escape: false);
})->with([
    'home' => [fn () => route('home'), 'home'],
    'results' => [fn () => route('results'), 'results'],
    'districts' => [fn () => route('districts.index'), 'districts_index'],
    'a district' => [fn () => route('districts.show', Electorate::factory()->create()->slug), 'district'],
    'questions' => [fn () => route('policies.index'), 'policies_index'],
    'a question' => [fn () => route('policies.show', Policy::factory()->published()->create()->slug), 'policy'],
    'methodology' => [fn () => route('methodology'), 'info'],
    'privacy' => [fn () => route('privacy'), 'info'],
    'about' => [fn () => route('about'), 'info'],
]);

it('loads no analytics when no project key is set', function (Closure $path) {
    config(['services.posthog.key' => null]);

    $this->get($path())->assertOk()->assertDontSee('data-posthog-key', escape: false);
})->with([
    'home' => fn () => route('home'),
    'results' => fn () => route('results'),
    'privacy' => fn () => route('privacy'),
]);

it('never loads analytics on previews, the contact form or error pages', function (Closure $path, int $status) {
    config(['services.posthog.key' => 'phc_test']);

    $this->get($path())
        ->assertStatus($status)
        ->assertDontSee('data-posthog-key', escape: false);
})->with([
    'preview quiz' => [fn () => URL::temporarySignedRoute('preview.quiz', now()->addDay(), absolute: false), 200],
    'preview results' => [fn () => URL::temporarySignedRoute('preview.results', now()->addDay(), absolute: false), 200],
    'contact form' => [fn () => route('contact'), 200],
    'a missing page' => [fn () => '/no-such-page', 404],
]);

it('keeps the page cacheable and cookie-free with analytics on', function () {
    config(['services.posthog.key' => 'phc_test']);

    $this->get(route('home'))->assertHeaderMissing('Set-Cookie');
});

it('tells visitors on the privacy page what is counted, what never is, and how to turn it off', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('Counting visits')
        ->assertSee('What we never count')
        ->assertSee('PostHog')
        ->assertSee('x-data="analyticsChoice"', escape: false)
        ->assertDontSee('No analytics');
});
