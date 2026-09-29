<?php

use App\Enums\PolicyStatus;
use App\Http\Controllers\PreviewController;
use App\Models\Policy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

function previewPath(string $route = 'preview.quiz', ?Carbon $expires = null): string
{
    return URL::temporarySignedRoute($route, $expires ?? now()->addDays(14), absolute: false);
}

it('forbids preview pages without a signature', function (string $path) {
    $this->get($path)->assertForbidden();
})->with(['/preview/quiz', '/preview/results', '/preview/stances.json']);

it('forbids a preview link after it expires', function () {
    $path = previewPath(expires: now()->addDay());
    $this->travel(2)->days();

    $this->get($path)->assertForbidden();
});

it('shows reviewers the quiz with a preview banner and keeps it out of caches, search and referrers', function (string $route) {
    $this->get(previewPath($route))
        ->assertSee('Preview.')
        ->assertDontSee('Prototype.')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', escape: false)
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Referrer-Policy', 'no-referrer');
})->with(['preview.quiz', 'preview.results']);

it('links the preview quiz to signed preview data and results that work until the same expiry', function () {
    Policy::factory()->create(['number' => 5, 'status' => PolicyStatus::Review]);
    $expires = now()->addDays(3);

    $page = $this->get(previewPath(expires: $expires))->getContent();

    preg_match('/data-stances-url="([^"]+)"/', $page, $stances);
    preg_match('/data-results-url="([^"]+)"/', $page, $results);
    expect(html_entity_decode($stances[1]))->toContain('expires='.$expires->getTimestamp());
    $this->get(html_entity_decode($stances[1]))->assertOk()->assertJsonPath('policies.0.id', 5);
    $this->get(html_entity_decode($results[1]))->assertOk();
});

it('includes policies still in review in the preview data but not dropped ones', function () {
    Policy::factory()->published()->create(['number' => 1]);
    Policy::factory()->create(['number' => 2, 'status' => PolicyStatus::Review]);
    Policy::factory()->create(['number' => 3, 'status' => PolicyStatus::Dropped]);

    $response = $this->get(previewPath('preview.stances'));

    expect(array_column($response->json('policies'), 'id'))->toBe([1, 2]);
    $response->assertHeader('Cache-Control', 'no-store, private');
});

it('creates preview links for the admin panel that open the preview quiz', function () {
    $link = PreviewController::linkUntil(now()->addDays(14));

    expect($link)->toStartWith(url('/preview/quiz?expires='));
    $this->get($link)->assertOk()->assertSee('Preview.');
});
