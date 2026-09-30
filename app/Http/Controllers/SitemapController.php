<?php

namespace App\Http\Controllers;

use App\Enums\ElectorateKind;
use App\Models\Electorate;
use App\Models\Policy;
use Illuminate\Http\Response;

/**
 * The sitemap for search engines: the fixed pages, every district and every
 * published question. Questions still in review are never listed.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [
            route('home'),
            route('quiz'),
            route('districts.index'),
            route('policies.index'),
            route('methodology'),
            route('privacy'),
            route('about'),
            ...Electorate::query()->where('kind', ElectorateKind::District)->orderBy('slug')->pluck('slug')->map(fn (string $slug): string => route('districts.show', $slug)),
            ...Policy::query()->published()->orderBy('number')->pluck('slug')->map(fn (string $slug): string => route('policies.show', $slug)),
        ];

        $entries = implode('', array_map(fn (string $url): string => '<url><loc>'.e($url).'</loc></url>', $urls));

        return response(
            '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$entries.'</urlset>'."\n",
            200,
            ['Content-Type' => 'application/xml'],
        );
    }
}
