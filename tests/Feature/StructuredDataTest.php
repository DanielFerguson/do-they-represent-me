<?php

use App\Models\Electorate;
use App\Models\Policy;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

/**
 * The page's schema.org graph, decoded from its JSON-LD block.
 *
 * @return list<array<string, mixed>>
 */
function jsonLdGraph(TestResponse $response): array
{
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $response->getContent(), $match);

    expect($match)->toHaveKey(1);

    return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR)['@graph'];
}

/**
 * The first node in the graph of the given schema.org type.
 *
 * @param  list<array<string, mixed>>  $graph
 * @return array<string, mixed>|null
 */
function jsonLdNode(array $graph, string $type): ?array
{
    return collect($graph)->first(fn (array $node): bool => in_array($type, (array) $node['@type'], true));
}

it('names the site for search engines on the home page', function () {
    $website = jsonLdNode(jsonLdGraph($this->get(route('home'))), 'WebSite');

    expect($website)
        ->name->toBe('Do They Represent Me?')
        ->url->toBe(route('home'));
});

it('describes each page with its schema.org type', function (string $route, string $type) {
    $graph = jsonLdGraph($this->get(route($route)));

    expect(jsonLdNode($graph, $type))->url->toBe(route($route));
})->with([
    'about' => ['about', 'AboutPage'],
    'contact' => ['contact', 'ContactPage'],
    'districts' => ['districts.index', 'CollectionPage'],
    'questions' => ['policies.index', 'CollectionPage'],
    'privacy' => ['privacy', 'WebPage'],
]);

it('gives question and district pages a breadcrumb trail', function (Closure $page) {
    [$url, $expected] = $page();

    $breadcrumb = jsonLdNode(jsonLdGraph($this->get($url)), 'BreadcrumbList');

    expect(collect($breadcrumb['itemListElement'])->map(fn (array $item): array => [$item['position'], $item['name'], $item['item']])->all())
        ->toBe($expected);
})->with([
    'a question' => fn () => [
        route('policies.show', Policy::factory()->published()->create(['slug' => 'short-stay-levy', 'title' => 'Short-stay levy'])->slug),
        [[1, 'Questions', route('policies.index')], [2, 'Short-stay levy', route('policies.show', 'short-stay-levy')]],
    ],
    'a district' => fn () => [
        route('districts.show', Electorate::factory()->create(['name' => 'Brunswick', 'slug' => 'brunswick'])->slug),
        [[1, 'Districts', route('districts.index')], [2, 'Brunswick District', route('districts.show', 'brunswick')]],
    ],
]);

it('lists every common question on the methodology page as an FAQ', function () {
    $response = $this->get(route('methodology'));

    $questions = collect(jsonLdNode(jsonLdGraph($response), 'FAQPage')['mainEntity'])->pluck('name');

    expect($questions)->toContain('Who chose these questions?', 'Why isn\'t my issue in the quiz?');
    $response->assertSeeInOrder($questions->all());
});

it('leaves structured data out of pages search engines should not index', function (Closure $path, int $status) {
    $this->get($path())
        ->assertStatus($status)
        ->assertDontSee('application/ld+json', escape: false);
})->with([
    'a preview' => [fn () => URL::temporarySignedRoute('preview.quiz', now()->addDay(), absolute: false), 200],
    'an error page' => [fn () => '/no-such-page', 404],
]);

it('escapes page text that could close the script element', function () {
    $policy = Policy::factory()->published()->create(['title' => 'Levy</script><script>alert(1)</script>']);

    $response = $this->get(route('policies.show', $policy->slug));

    $response->assertDontSee('</script><script>alert(1)', escape: false);
    expect(jsonLdNode(jsonLdGraph($response), 'WebPage'))->name->toBe('Levy</script><script>alert(1)</script>');
});
