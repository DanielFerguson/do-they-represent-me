@props([
    'title' => null,
    'noindex' => false,
    'canonical' => true,
    'description' => 'See how Victorian parties and MPs actually voted in State Parliament, and compare their record with your own views.',
])

@php
    $navLink = fn (bool $current): string => 'rounded py-1 underline-offset-4 hover:underline'.($current ? ' font-medium text-zinc-900 dark:text-zinc-100' : '');
@endphp

<!DOCTYPE html>
<html lang="en-AU">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ $description }}">
        <meta name="color-scheme" content="light dark">
        <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#09090b" media="(prefers-color-scheme: dark)">
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
        <meta property="og:image" content="{{ asset('images/share.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="Do They Represent Me? How Victoria's parties and MPs voted in State Parliament.">
        <meta name="twitter:card" content="summary_large_image">

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen flex-col">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:rounded focus:bg-zinc-900 focus:px-3 focus:py-2 focus:text-white dark:focus:bg-zinc-100 dark:focus:text-zinc-900">Skip to content</a>

        <header class="border-b border-zinc-200 dark:border-zinc-800">
            <div class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-4">
                <a href="{{ route('home') }}" class="rounded py-1 font-semibold tracking-tight">Do They Represent Me?</a>
                <nav aria-label="Main" class="flex flex-wrap justify-end gap-x-4 text-sm text-zinc-600 dark:text-zinc-400">
                    <a href="{{ route('districts.index') }}" @if (request()->routeIs('districts.*')) aria-current="page" @endif class="{{ $navLink(request()->routeIs('districts.*')) }}">Your district</a>
                    <a href="{{ route('policies.index') }}" @if (request()->routeIs('policies.*')) aria-current="page" @endif class="{{ $navLink(request()->routeIs('policies.*')) }}">Questions</a>
                    <a href="{{ route('quiz') }}" @if (request()->routeIs('quiz')) aria-current="page" @endif class="{{ $navLink(request()->routeIs('quiz')) }}">Take the quiz</a>
                </nav>
            </div>
        </header>

        <main id="main" class="flex-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-zinc-200 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
            <div class="mx-auto flex max-w-3xl flex-col gap-4 px-4 py-6">
                <nav aria-label="About this site" class="flex flex-wrap gap-x-4">
                    <a href="{{ route('methodology') }}" @if (request()->routeIs('methodology')) aria-current="page" @endif class="{{ $navLink(request()->routeIs('methodology')) }}">Methodology</a>
                    <a href="{{ route('privacy') }}" @if (request()->routeIs('privacy')) aria-current="page" @endif class="{{ $navLink(request()->routeIs('privacy')) }}">Privacy</a>
                    <a href="{{ route('about') }}" @if (request()->routeIs('about')) aria-current="page" @endif class="{{ $navLink(request()->routeIs('about')) }}">About and corrections</a>
                </nav>
                <div class="flex flex-col gap-1">
                    <p>Voting records come from the Parliament of Victoria's Votes and Proceedings and Minutes of the Proceedings, 60th Parliament (2022–2026).</p>
                    <p>Your answers stay in your browser. They are never sent to us.</p>
                    @if (config('site.authorisation'))
                        <p>{{ config('site.authorisation') }}</p>
                    @endif
                </div>
            </div>
        </footer>
    </body>
</html>
