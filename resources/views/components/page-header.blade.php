@props(['eyebrow', 'title'])

{{-- The eyebrow, title and lede that open the methodology, about and privacy pages. --}}
<header {{ $attributes->class('flex flex-col gap-2.5 lg:gap-4') }}>
    <p class="eyebrow">{{ $eyebrow }}</p>
    <h1 class="text-h1-mobile font-semibold tracking-display lg:text-h1">{{ $title }}</h1>
    <p class="text-[15px] leading-[23px] text-ink-muted lg:text-body">{{ $slot }}</p>
</header>
