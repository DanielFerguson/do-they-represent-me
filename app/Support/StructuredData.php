<?php

namespace App\Support;

/**
 * The schema.org description of a public page, as JSON-LD for search engines.
 *
 * Every indexable page describes the site and itself. A page can give a more
 * specific type, the pages above it for a breadcrumb trail, and extra
 * properties such as the questions of an FAQ.
 */
class StructuredData
{
    /**
     * @param  string|list<string>  $type  the schema.org type of the page, such as AboutPage
     * @param  list<array{name: string, url: string}>  $parents  the pages above this one, top first
     * @param  array<string, mixed>  $properties  extra schema.org properties for the page
     * @return array{'@context': string, '@graph': list<array<string, mixed>>}
     */
    public static function graph(string|array $type, string $name, string $description, string $url, string $image, array $parents = [], array $properties = []): array
    {
        $home = route('home');

        $nodes = [
            [
                '@type' => 'WebSite',
                '@id' => $home.'#website',
                'name' => 'Do They Represent Me?',
                'url' => $home,
                'inLanguage' => 'en-AU',
                'publisher' => ['@id' => $home.'#publisher'],
            ],
            [
                '@type' => 'Person',
                '@id' => $home.'#publisher',
                'name' => 'Dan Ferguson',
                'url' => 'https://danferg.com',
            ],
            [
                '@type' => $type,
                '@id' => $url.'#webpage',
                'url' => $url,
                'name' => $name,
                'description' => $description,
                'inLanguage' => 'en-AU',
                'isPartOf' => ['@id' => $home.'#website'],
                'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $image],
                ...($parents === [] ? [] : ['breadcrumb' => ['@id' => $url.'#breadcrumb']]),
                ...$properties,
            ],
        ];

        if ($parents !== []) {
            $trail = [...$parents, ['name' => $name, 'url' => $url]];

            $nodes[] = [
                '@type' => 'BreadcrumbList',
                '@id' => $url.'#breadcrumb',
                'itemListElement' => array_map(fn (array $page, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $page['name'],
                    'item' => $page['url'],
                ], $trail, array_keys($trail)),
            ];
        }

        return ['@context' => 'https://schema.org', '@graph' => $nodes];
    }

    /**
     * The graph as JSON that is safe inside a script element: "<" and ">" are
     * escaped, so no page text can close the element.
     *
     * @param  array<string, mixed>  $graph
     */
    public static function encode(array $graph): string
    {
        return json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_THROW_ON_ERROR);
    }
}
