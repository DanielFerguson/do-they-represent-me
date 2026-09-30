@props([
    'title' => null,
    'noindex' => false,
    'canonical' => true,
    'description' => 'See how Victorian parties and MPs actually voted in State Parliament, and compare their record with your own views.',
    'showRecordsDate' => true,
    // A path under public/, or a full address for a picture made for the page.
    'shareImage' => 'images/share.png',
    'shareImageAlt' => "Do They Represent Me? How Victoria's parties and MPs voted in State Parliament.",
    // Set on the pages that count visits. Left empty on previews, the contact
    // form and error pages, which never load analytics.
    'pageType' => null,
])

@php
    $primaryLinks = [
        ['label' => 'Quiz', 'route' => 'home', 'current' => request()->routeIs('home', 'results')],
        ['label' => 'Your district', 'route' => 'districts.index', 'current' => request()->routeIs('districts.*')],
        ['label' => 'Questions', 'route' => 'policies.index', 'current' => request()->routeIs('policies.*')],
        ['label' => 'Methodology', 'route' => 'methodology', 'current' => request()->routeIs('methodology')],
        ['label' => 'About', 'route' => 'about', 'current' => request()->routeIs('about')],
    ];
    $footerLinks = [
        ['label' => 'Methodology', 'route' => 'methodology'],
        ['label' => 'About', 'route' => 'about'],
        ['label' => 'Contact', 'route' => 'contact'],
        ['label' => 'Privacy', 'route' => 'privacy'],
    ];
    $recordsUpTo = $showRecordsDate ? $latestSittingDate() : null;
@endphp

<!DOCTYPE html>
<html lang="en-AU">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ $description }}">
        <meta name="color-scheme" content="light dark">
        <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0a0a0a" media="(prefers-color-scheme: dark)">
        @if ($noindex)
            <meta name="robots" content="noindex, nofollow">
        @elseif ($canonical)
            <link rel="canonical" href="{{ url()->current() }}">
        @endif

        <title>{{ $title ? $title.' · ' : '' }}Do They Represent Me?</title>

        <meta property="og:site_name" content="Do They Represent Me?">
        <meta property="og:type" content="website">
        <meta property="og:locale" content="en_AU">
        <meta property="og:title" content="{{ $title ?? 'Do They Represent Me?' }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:image" content="{{ str_starts_with($shareImage, 'http') ? $shareImage : asset($shareImage) }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ $shareImageAlt }}">
        <meta name="twitter:card" content="summary_large_image">

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen flex-col bg-ground text-ink" @if ($pageType && config('services.posthog.key')) data-posthog-key="{{ config('services.posthog.key') }}" data-page-type="{{ $pageType }}" @endif>
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-30 focus:rounded-md focus:bg-ink focus:px-3 focus:py-2 focus:text-ground">Skip to content</a>

        <header x-data="menu" x-on:keydown.escape="close" class="relative">
            <div class="mx-auto flex h-15 max-w-page items-center justify-between gap-6 px-5 lg:h-18">
                <a href="{{ route('home') }}" class="flex items-baseline gap-3 rounded-sm py-1">
                    <span class="font-semibold tracking-[-0.01em] lg:text-body">Do They Represent Me?</span>
                    <span class="eyebrow hidden sm:inline">Victoria 2026</span>
                </a>

                <nav aria-label="Main" class="hidden lg:block">
                    <ul class="flex items-center gap-8 text-small">
                        @foreach ($primaryLinks as $link)
                            <li>
                                <a href="{{ route($link['route']) }}" @if ($link['current']) aria-current="page" @endif @class([
                                    'block border-b-[1.5px] py-1',
                                    'border-ink font-medium text-ink' => $link['current'],
                                    'border-transparent text-ink-muted hover:text-ink' => ! $link['current'],
                                ])>{{ $link['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>

                <button type="button" x-ref="button" x-on:click="toggle" x-bind:aria-expanded="open" aria-controls="menu" class="flex h-9 items-center gap-2 rounded-md border border-rule-strong px-3 text-small font-medium lg:hidden">
                    <svg x-show="!open" width="14" height="10" viewBox="0 0 14 10" aria-hidden="true" class="shrink-0"><path d="M0 1h14M0 5h14M0 9h14" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>
                    <svg x-show="open" x-cloak width="12" height="12" viewBox="0 0 12 12" aria-hidden="true" class="shrink-0"><path d="M1.5 1.5L10.5 10.5M10.5 1.5L1.5 10.5" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>
                    <span x-text="buttonLabel">Menu</span>
                </button>
            </div>

            <x-party-stripe />

            <nav id="menu" aria-label="Main" x-show="open" x-cloak class="absolute inset-x-0 top-full z-20 min-h-[calc(100dvh-64px)] bg-ground px-5 pb-16 lg:hidden">
                <ul>
                    @foreach ($primaryLinks as $link)
                        <li>
                            <a href="{{ route($link['route']) }}" @if ($link['current']) aria-current="page" @endif @class([
                                'flex h-15 items-center justify-between border-b border-rule text-h2 text-ink',
                                'border-l-3 border-l-ink pl-3 font-semibold' => $link['current'],
                                'pl-[15px] font-medium' => ! $link['current'],
                            ])>
                                {{ $link['label'] }}
                                @if ($link['current'])
                                    <span class="text-[13px] font-normal leading-4 text-ink-muted">You are here</span>
                                @else
                                    <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" class="shrink-0 text-ink-muted"><path d="M5 2.5L9.5 7L5 11.5" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
                <ul class="mt-6 flex gap-6 pl-[15px] text-[15px] text-ink-muted">
                    <li><a href="{{ route('contact') }}" class="hover:text-ink">Contact</a></li>
                    <li><a href="{{ route('privacy') }}" class="hover:text-ink">Privacy</a></li>
                </ul>
                <div class="mt-8 flex flex-col gap-1.5 rounded-md bg-surface p-4">
                    <p class="text-small font-semibold">Your answers stay in your browser</p>
                    <p class="text-[13px] leading-[19px] text-ink-muted">They are never sent to us. Close the menu to carry on where you left off.</p>
                </div>
            </nav>
        </header>

        <main id="main" class="flex-1">
            {{ $slot }}
        </main>

        <footer>
            <x-party-stripe />
            <div class="mx-auto flex max-w-page flex-col-reverse gap-5 px-5 pb-10 pt-8 lg:flex-row lg:justify-between lg:gap-16 lg:pb-12 lg:pt-10">
                <div class="flex max-w-[560px] flex-col gap-2 text-[13px] leading-5 text-ink-muted lg:text-small lg:leading-[21px]">
                    <p>
                        Voting records from the Parliament of Victoria's Votes and Proceedings and Minutes of the Proceedings, 60th Parliament (2022–2026).@if ($recordsUpTo) Records up to {{ $recordsUpTo->format('j F Y') }}.@endif
                    </p>
                    <p>Your answers stay in your browser. They are never sent to us.</p>
                    <p>An independent project. Not affiliated with any party, candidate or the Parliament.</p>
                    @if (config('site.authorisation'))
                        <p class="pt-1 text-label leading-[18px]">{{ config('site.authorisation') }}</p>
                    @endif
                </div>
                <nav aria-label="About this site">
                    <ul class="flex flex-wrap gap-x-6 gap-y-3 text-small">
                        @foreach ($footerLinks as $link)
                            <li><a href="{{ route($link['route']) }}" @if (request()->routeIs($link['route'])) aria-current="page" class="font-medium underline underline-offset-4" @else class="link decoration-transparent" @endif>{{ $link['label'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            </div>
        </footer>
    </body>
</html>
