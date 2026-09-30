@props(['title', 'heading'])

{{-- Error pages use the public layout, which the site's Content-Security-Policy allows. They never touch the database, so they still render when it is down. --}}
<x-layouts.public :title="$title" :canonical="false" :show-records-date="false">
    <div class="mx-auto max-w-page px-5 pb-12 pt-8 lg:pb-24 lg:pt-18">
        <div class="flex max-w-reading flex-col gap-6 lg:gap-8">
            <div class="flex flex-col gap-2.5 lg:gap-4">
                <h1 class="text-h1-mobile font-semibold tracking-display lg:text-h1">{{ $heading }}</h1>
                <div class="flex flex-col gap-3 text-[15px] leading-[23px] text-ink-muted lg:text-body">
                    {{ $slot }}
                </div>
            </div>
            <ul class="flex flex-wrap gap-x-6 gap-y-3 border-t border-ink pt-5 text-small">
                <li><a href="{{ route('home') }}" class="link">Take the quiz</a></li>
                <li><a href="{{ route('districts.index') }}" class="link">Find your district</a></li>
                <li><a href="{{ route('policies.index') }}" class="link">The questions</a></li>
            </ul>
        </div>
    </div>
</x-layouts.public>
