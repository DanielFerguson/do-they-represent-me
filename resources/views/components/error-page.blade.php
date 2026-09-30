@props(['title', 'heading'])

{{-- Error pages use the public layout, which the site's Content-Security-Policy allows. They never touch the database, so they still render when it is down. --}}
<x-layouts.public :title="$title" :canonical="false">
    <div class="mx-auto flex max-w-2xl flex-col gap-4 px-4 py-16">
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ $heading }}</h1>
        <div class="flex flex-col gap-3 text-zinc-700 dark:text-zinc-300">
            {{ $slot }}
        </div>
        <p class="flex flex-wrap gap-x-4 gap-y-1">
            <a href="{{ route('home') }}" class="underline underline-offset-4">Home</a>
            <a href="{{ route('districts.index') }}" class="underline underline-offset-4">Find your district</a>
            <a href="{{ route('policies.index') }}" class="underline underline-offset-4">The questions</a>
        </p>
    </div>
</x-layouts.public>
