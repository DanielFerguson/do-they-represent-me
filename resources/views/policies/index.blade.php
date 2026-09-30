@php
    $questionCount = $topics->flatten()->count();
    $chevron = '<svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" class="shrink-0 text-ink-muted"><path d="M5 2.5L9.5 7L5 11.5" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>';
@endphp

<x-layouts.public page-type="policies_index" share-image="images/share-questions.png" title="Questions">
    <div class="mx-auto flex max-w-page flex-col gap-6 px-5 pb-12 pt-5 lg:flex-row lg:items-start lg:gap-24 lg:pb-24 lg:pt-18">
        <div class="flex min-w-0 flex-col gap-6 lg:w-[704px] lg:shrink-0 lg:gap-12">
            <header class="flex flex-col gap-2.5 lg:gap-4">
                @if ($questionCount > 0)
                    <p class="eyebrow">{{ $questionCount }} {{ Str::plural('question', $questionCount) }} · {{ $topics->count() }} {{ Str::plural('topic', $topics->count()) }}</p>
                @endif
                <h1 class="text-h1-mobile font-semibold tracking-display lg:text-h1">The questions</h1>
                <p class="text-[15px] leading-[23px] text-ink-muted lg:text-body">
                    Each question is linked to real votes in the Parliament of Victoria. Open one to see those votes, how each party voted, and what each side said.
                    <a href="{{ route('methodology') }}" class="link">How the questions were chosen</a>
                </p>
            </header>

            @if ($topics->isEmpty())
                <p role="status" class="rounded-md bg-surface p-5 text-small leading-[21px] text-ink-muted">
                    The questions are being checked by reviewers and will be published here soon.
                </p>
            @else
                <details class="group lg:hidden">
                    <summary class="flex h-12 cursor-pointer list-none items-center justify-between rounded-md border border-rule-strong px-3.5 text-[15px] [&::-webkit-details-marker]:hidden">
                        Jump to a topic
                        <svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true" class="shrink-0 transition-transform group-open:rotate-180 motion-reduce:transition-none"><path d="M2.5 4L6 7.5L9.5 4" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>
                    </summary>
                    <ul class="mt-2 flex flex-col rounded-md border border-rule-strong px-3.5">
                        @foreach ($topics as $topic => $policies)
                            <li class="border-t border-rule first:border-t-0">
                                <a href="#topic-{{ $loop->index }}" class="flex h-11 items-center justify-between gap-4 text-[15px]">
                                    {{ $topic }}
                                    <span class="text-[13px] leading-4 text-ink-muted">{{ $policies->count() }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </details>

                @foreach ($topics as $topic => $policies)
                    <section aria-labelledby="topic-{{ $loop->index }}" class="flex scroll-mt-6 flex-col border-t border-ink">
                        <div class="flex items-baseline justify-between gap-4 pb-1.5 pt-3.5 lg:pb-2 lg:pt-4">
                            <h2 id="topic-{{ $loop->index }}" class="text-[18px] font-semibold leading-[26px] lg:text-h2 lg:leading-7">{{ $topic }}</h2>
                            <span class="text-[13px] leading-4 text-ink-muted">{{ $policies->count() }}</span>
                        </div>
                        <ul class="flex flex-col">
                            @foreach ($policies as $policy)
                                @php
                                    $voteCount = $policy->divisions->count();
                                    $houses = $policy->divisions->pluck('house')->filter()->unique('id');
                                    $coverage = match ($houses->count()) {
                                        0 => null,
                                        1 => Str::after($houses->first()->name, 'Legislative ').' only',
                                        default => 'both houses',
                                    };
                                    $votes = implode(' · ', array_filter([$voteCount > 0 ? $voteCount.' '.Str::plural('vote', $voteCount) : null, $coverage]));
                                @endphp
                                <li class="border-t border-rule">
                                    <a href="{{ route('policies.show', $policy->slug) }}" class="group flex items-center gap-3.5 py-3.5 lg:gap-5 lg:py-4">
                                        <span class="flex min-w-0 flex-1 flex-col gap-1">
                                            <span class="text-[15px] font-medium leading-[22px] group-hover:underline group-hover:decoration-rule-strong group-hover:underline-offset-4 lg:text-base lg:leading-6">{{ $policy->question }}</span>
                                            <span class="text-[13px] leading-4 text-ink-muted lg:text-small lg:leading-[18px]">
                                                @if ($votes === '')
                                                    {{ $policy->title }}
                                                @else
                                                    <span class="hidden lg:inline">{{ $policy->title }} · </span>{{ $votes }}
                                                @endif
                                            </span>
                                        </span>
                                        {!! $chevron !!}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            @endif
        </div>

        <aside class="flex flex-col gap-8 lg:w-80 lg:shrink-0" aria-label="Topics and the quiz">
            @if ($topics->isNotEmpty())
                <nav aria-labelledby="rail-topics" class="hidden flex-col lg:flex">
                    <h2 id="rail-topics" class="eyebrow border-b border-rule pb-3">Topics</h2>
                    <ul>
                        @foreach ($topics as $topic => $policies)
                            <li>
                                <a href="#topic-{{ $loop->index }}" class="group flex min-h-9 items-center justify-between gap-4 py-2 text-small leading-[18px]">
                                    <span class="group-hover:underline group-hover:decoration-rule-strong group-hover:underline-offset-4">{{ $topic }}</span>
                                    <span class="text-[13px] leading-4 text-ink-muted">{{ $policies->count() }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif
            <div class="flex flex-col gap-2.5 rounded-md bg-surface p-4 lg:gap-3 lg:p-5">
                <p class="text-small font-semibold leading-[18px]">Answer them yourself</p>
                <p class="hidden text-small leading-[21px] text-ink-muted lg:block">See which parties voted the way you would have.</p>
                <a href="{{ route('home') }}" class="flex h-11 items-center justify-center rounded-md bg-ink text-small font-medium text-ground hover:opacity-85">Take the quiz</a>
            </div>
        </aside>
    </div>
</x-layouts.public>
