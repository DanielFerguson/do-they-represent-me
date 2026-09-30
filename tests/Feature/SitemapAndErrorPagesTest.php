<?php

use App\Enums\PolicyStatus;
use App\Models\Electorate;
use App\Models\Policy;
use Illuminate\Support\Facades\URL;

it('lists the fixed pages, every district and only the published questions in the sitemap', function () {
    $district = Electorate::factory()->create();
    Electorate::factory()->region()->create(['slug' => 'a-region']);
    $published = Policy::factory()->published()->create();
    $review = Policy::factory()->create(['status' => PolicyStatus::Review]);

    $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee('<loc>'.route('methodology').'</loc>', escape: false)
        ->assertSee('<loc>'.route('districts.show', $district->slug).'</loc>', escape: false)
        ->assertSee('<loc>'.route('policies.show', $published->slug).'</loc>', escape: false)
        ->assertDontSee($review->slug)
        ->assertDontSee('a-region');
});

it('shows a page-not-found page in the site’s own layout', function () {
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertSee('Page not found')
        ->assertSee(route('districts.index'), escape: false)
        ->assertDontSee('rel="canonical"', escape: false);
});

it('explains an expired preview link', function () {
    $this->get(URL::temporarySignedRoute('preview.quiz', now()->subMinute(), absolute: false))
        ->assertForbidden()
        ->assertSee('it may have expired');
});

it('keeps admin pages and previews out of search engines', function () {
    expect(file_get_contents(public_path('robots.txt')))
        ->toContain('Disallow: /admin')
        ->toContain('Disallow: /preview/')
        ->toContain('Sitemap: https://dotheyrepresentme.com/sitemap.xml');
});
