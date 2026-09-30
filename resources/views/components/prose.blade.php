@props(['large' => false])

{{-- Running text inside a section of the methodology, about and privacy pages. Large text is the about page's reading size on desktop. --}}
<div {{ $attributes->class([
    'flex flex-col gap-2.5 text-[15px] leading-6 lg:gap-3',
    'lg:text-base lg:leading-[26px]' => ! $large,
    'lg:text-body lg:leading-7' => $large,
    '[&_a]:underline [&_a]:decoration-rule-strong [&_a]:decoration-1 [&_a]:underline-offset-4 [&_a:hover]:decoration-current',
    '[&_code]:text-[0.9em]',
    '[&_h3]:mt-2 [&_h3]:font-semibold',
    '[&_ul]:flex [&_ul]:list-disc [&_ul]:flex-col [&_ul]:gap-1.5 [&_ul]:pl-5',
]) }}>
    {{ $slot }}
</div>
