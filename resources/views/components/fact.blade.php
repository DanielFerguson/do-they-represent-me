@props(['term', 'wide' => false])

{{-- One row of a definition list (<dl>) on the methodology and privacy pages: the term above its description on phones, beside it on desktop. --}}
<div {{ $attributes->class('flex flex-col gap-0.5 border-t border-rule py-2.5 last:border-b lg:flex-row lg:gap-4') }}>
    <dt @class(['text-small font-semibold leading-[18px] lg:shrink-0', 'lg:w-50' => $wide, 'lg:w-45' => ! $wide])>{{ $term }}</dt>
    <dd class="text-[13px] leading-5 text-ink-muted lg:text-small lg:leading-[21px]">{{ $slot }}</dd>
</div>
