@props([
    'title' => null,
    'noindex' => false,
    'description' => 'See how Victorian parties and MPs actually voted in State Parliament, and compare their record with your own views.',
])

<!DOCTYPE html>
<html lang="en-AU">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ $description }}">
        <meta name="color-scheme" content="light dark">
        @if ($noindex)
            <meta name="robots" content="noindex, nofollow">
        @endif

        <title>{{ $title ? $title.' · ' : '' }}Do They Represent Me?</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen flex-col">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:rounded focus:bg-zinc-900 focus:px-3 focus:py-2 focus:text-white">Skip to content</a>

        <header class="border-b border-zinc-200 dark:border-zinc-800">
            <div class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-4">
                <a href="{{ route('home') }}" class="font-semibold tracking-tight">Do They Represent Me?</a>
                <nav aria-label="Main">
                    <a href="{{ route('quiz') }}" class="text-sm text-zinc-600 underline-offset-4 hover:underline dark:text-zinc-400">Take the quiz</a>
                </nav>
            </div>
        </header>

        <main id="main" class="flex-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-zinc-200 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
            <div class="mx-auto flex max-w-3xl flex-col gap-1 px-4 py-6">
                <p>Voting records come from the Parliament of Victoria's Votes and Proceedings and Minutes of the Proceedings, 60th Parliament (2022–2026).</p>
                <p>Your answers stay in your browser. They are never sent to us.</p>
            </div>
        </footer>
    </body>
</html>
