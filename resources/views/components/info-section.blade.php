@props(['id', 'title', 'number' => null, 'first' => false])

{{-- One section of the methodology, about or privacy page, with an optional number in its own column on desktop. The first section is ruled in ink. --}}
<section id="{{ $id }}" aria-labelledby="{{ $id }}-heading" {{ $attributes->class([
    'flex scroll-mt-6 flex-col gap-2.5 border-t pt-3.5 lg:pt-5',
    'lg:flex-row lg:gap-6' => $number !== null,
    'lg:gap-3' => $number === null,
    'border-ink' => $first,
    'border-rule' => ! $first,
]) }}>
    @if ($number !== null)
        <span class="hidden w-10 shrink-0 pt-1 text-small font-semibold leading-[18px] lg:block" aria-hidden="true">{{ $number }}</span>
    @endif
    <div class="flex min-w-0 flex-1 flex-col gap-2.5 lg:gap-3">
        <h2 id="{{ $id }}-heading" class="flex items-baseline gap-3 text-[18px] font-semibold leading-[25px] lg:text-h2 lg:leading-7">
            @if ($number !== null)
                <span class="text-[13px] leading-4 lg:hidden">{{ $number }}</span>
            @endif
            {{ $title }}
        </h2>
        {{ $slot }}
    </div>
</section>
