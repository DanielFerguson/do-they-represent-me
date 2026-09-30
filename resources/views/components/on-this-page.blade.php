@props(['sections', 'disclosure' => false])

{{--
    Links to each section of a long page, numbered to match the section headings.
    Desktop shows it as a rail beside the page; phones get a disclosure under the title.

    @var array<string, string> $sections  section id => short label
--}}
@if ($disclosure)
    <details {{ $attributes->class('group rounded-md border border-rule-strong lg:hidden') }}>
        <summary class="flex h-12 cursor-pointer list-none items-center justify-between gap-3 rounded-md px-3.5 text-small [&::-webkit-details-marker]:hidden">
            On this page · {{ count($sections) }} sections
            <svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true" class="shrink-0 group-open:rotate-180"><path d="M2.5 4L6 7.5L9.5 4" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>
        </summary>
        <ol class="border-t border-rule px-3.5 py-1.5">
            @foreach ($sections as $id => $label)
                <li>
                    <a href="#{{ $id }}" class="flex h-10 items-center gap-3 text-small">
                        <span class="w-5 shrink-0 text-[13px] leading-4 text-ink-muted">{{ sprintf('%02d', $loop->iteration) }}</span>
                        {{ $label }}
                    </a>
                </li>
            @endforeach
        </ol>
    </details>
@else
    <nav aria-labelledby="on-this-page" {{ $attributes->class('flex flex-col') }}>
        <h2 id="on-this-page" class="eyebrow border-b border-rule pb-3">On this page</h2>
        <ol>
            @foreach ($sections as $id => $label)
                <li>
                    <a href="#{{ $id }}" class="flex h-9 items-center gap-3 border-l-2 border-rule pl-3 text-small leading-[18px] text-ink-muted hover:border-ink hover:text-ink">
                        <span class="w-5 shrink-0 text-[13px] leading-4">{{ sprintf('%02d', $loop->iteration) }}</span>
                        {{ $label }}
                    </a>
                </li>
            @endforeach
        </ol>
    </nav>
@endif
