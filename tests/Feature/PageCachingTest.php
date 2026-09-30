<?php

use App\Models\Policy;
use Illuminate\Support\Facades\URL;

const PAGE_CACHE_CONTROL = 'max-age=60, public, s-maxage=300, stale-while-revalidate=86400';

it('lets browsers and the edge cache public pages briefly', function (Closure $path) {
    $response = $this->get($path());

    $response->assertOk()->assertHeader('Cache-Control', PAGE_CACHE_CONTROL);
    expect($response->headers->get('ETag'))->not->toBeNull();
})->with([
    'home' => fn () => route('home'),
    'results' => fn () => route('results'),
    'districts' => fn () => route('districts.index'),
    'a question' => fn () => route('policies.show', Policy::factory()->published()->create()->slug),
    'methodology' => fn () => route('methodology'),
    'sitemap' => fn () => route('sitemap'),
]);

it('answers a repeat request for an unchanged page with 304 Not Modified', function () {
    $etag = $this->get(route('about'))->headers->get('ETag');

    $this->get(route('about'), ['If-None-Match' => $etag])->assertStatus(304);
});

it('never caches errors, previews or the versioned data files as pages', function () {
    expect($this->get(route('policies.show', 'not-published'))->assertNotFound()->headers->get('Cache-Control'))->not->toContain('public');

    $this->get(URL::temporarySignedRoute('preview.quiz', now()->addDay(), absolute: false))->assertHeader('Cache-Control', 'no-store, private');
});
