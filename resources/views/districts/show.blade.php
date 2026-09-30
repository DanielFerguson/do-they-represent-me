@php
    $sectionClass = 'flex flex-col gap-3 border-t border-ink pt-3.5 lg:gap-4 lg:pt-5';
    $headingClass = 'text-body font-semibold leading-6 lg:text-h2 lg:leading-7';
    $noteClass = 'rounded-md bg-surface p-4 text-[13px] leading-[19px] text-ink-muted lg:p-5 lg:text-small lg:leading-[21px]';
    $reportUrl = route('contact', ['topic' => 'correction', 'district' => $district->slug]);
@endphp

<x-layouts.public :title="$district->name.' District'" :description="'The MLA and MLCs for '.$district->name.' District, and how they voted in the Parliament of Victoria.'">
    <div class="mx-auto flex max-w-page flex-col gap-6 px-5 pb-12 pt-5 lg:flex-row lg:items-start lg:gap-24 lg:pb-24 lg:pt-18">
        <div class="flex min-w-0 flex-col gap-6 lg:w-[704px] lg:shrink-0 lg:gap-12">
            <header class="flex flex-col gap-2.5 lg:gap-4">
                <nav aria-label="Breadcrumb" class="flex items-baseline gap-1.5 text-[13px] leading-4 text-ink-muted lg:gap-2 lg:text-small lg:leading-[18px]">
                    <a href="{{ route('districts.index') }}" class="link">Districts</a>
                    <span aria-hidden="true" class="text-rule-strong">/</span>
                    {{ $region?->name }} Region
                </nav>
                <h1 class="text-h1-mobile font-semibold tracking-display lg:text-h1">{{ $district->name }} District</h1>
                <p class="text-[15px] leading-[23px] text-ink-muted lg:text-body">
                    Voters here elect one member of the Legislative Assembly, and help elect the five members of the Legislative Council for {{ $region?->name }} Region.
                </p>
                <div
                    x-data="myDistrict"
                    data-district="{{ $district->slug }}"
                    class="flex flex-col gap-2 pt-1 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3"
                >
                    <a href="{{ route('home') }}" class="flex h-12 items-center justify-center rounded-md bg-ink px-5 text-[15px] font-medium text-ground hover:opacity-85">Take the quiz</a>
                    <button type="button" x-cloak x-on:click="choose" x-bind:aria-pressed="isMine" class="group flex h-12 items-center justify-center gap-2 rounded-md border border-rule-strong px-5 text-[15px] font-medium hover:border-ink aria-pressed:border-ink">
                        <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" class="hidden shrink-0 group-aria-pressed:block"><path d="M2.5 7.5L5.5 10.5L11.5 3.5" fill="none" stroke="currentColor" stroke-width="1.75" /></svg>
                        Use this as my district
                    </button>
                    <p role="status" class="text-[13px] leading-[19px] text-ink-muted lg:text-small" x-text="status"></p>
                </div>
            </header>

            <section aria-labelledby="assembly" class="{{ $sectionClass }}">
                <div class="flex items-baseline justify-between gap-4">
                    <h2 id="assembly" class="{{ $headingClass }}">Legislative Assembly</h2>
                    <p class="hidden text-[13px] leading-4 text-ink-muted sm:block">60th Parliament, 2022–2026</p>
                </div>

                @if ($member)
                    <x-member-record :record="$member" :policy-count="$policyCount" :role="'Member for '.$district->name" />
                @elseif ($vacancy)
                    <p class="{{ $noteClass }}">
                        This seat is vacant. {{ $vacancy->member->display_name }} ({{ $vacancy->party->display_name ?? $vacancy->party->name }}) was the member until {{ $vacancy->ends_on?->format('j F Y') }}.
                    </p>
                @else
                    <p class="{{ $noteClass }}">No member is recorded for this district.</p>
                @endif
            </section>

            @if ($region)
                <section aria-labelledby="council" class="{{ $sectionClass }}">
                    <div class="flex flex-col gap-0.5 lg:gap-1">
                        <h2 id="council" class="{{ $headingClass }}">Legislative Council<span class="hidden lg:inline"> · {{ $region->name }}</span></h2>
                        <p class="text-[13px] leading-4 text-ink-muted lg:text-small lg:leading-[18px]">
                            <span class="lg:hidden">{{ $region->name }} Region · five members, in alphabetical order</span>
                            <span class="hidden lg:inline">Five members for the region in the 60th Parliament (2022–2026), in alphabetical order.</span>
                        </p>
                    </div>

                    @if ($regionMembers === [])
                        <p class="{{ $noteClass }}">No members are recorded for this region.</p>
                    @else
                        <div class="flex flex-col rounded-md border border-rule-strong px-4 lg:px-6">
                            @foreach ($regionMembers as $record)
                                <x-member-record compact :record="$record" :policy-count="$policyCount" :role="'Member for '.$region->name" />
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif

            @if ($election)
                <section aria-labelledby="candidates" class="{{ $sectionClass }}">
                    <div class="flex flex-col gap-0.5 lg:gap-1">
                        <h2 id="candidates" class="{{ $headingClass }}">Candidates at the {{ $election->name }}</h2>
                        @if ($candidates->isNotEmpty() || $regionCandidates->isNotEmpty())
                            <p class="text-[13px] leading-4 text-ink-muted lg:text-small lg:leading-[18px]">In ballot paper order, as published by the Victorian Electoral Commission.</p>
                        @endif
                    </div>

                    @if ($candidates->isEmpty() && $regionCandidates->isEmpty())
                        <div class="flex flex-col gap-1 rounded-md bg-surface p-4 lg:gap-1.5 lg:p-5">
                            <p class="text-small font-medium leading-[18px] lg:text-[15px]">Not published yet</p>
                            <p class="text-[13px] leading-[19px] text-ink-muted lg:text-small lg:leading-[21px]">The Victorian Electoral Commission publishes the candidates after nominations close on 9 November. They'll appear here in ballot paper order.</p>
                        </div>
                    @endif

                    @if ($candidates->isNotEmpty())
                        <div class="rounded-md border border-rule-strong px-4 py-1 lg:px-6">
                            <x-candidate-list :candidates="$candidates" :label="$district->name.' District (Legislative Assembly)'" />
                        </div>
                    @endif

                    @if ($regionCandidates->isNotEmpty())
                        <details class="group">
                            <summary class="flex cursor-pointer list-none items-center gap-2 text-small leading-[18px] [&::-webkit-details-marker]:hidden">
                                <svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true" class="shrink-0 transition-transform group-open:rotate-90 motion-reduce:transition-none"><path d="M4 2.5L7.5 6L4 9.5" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>
                                <span class="link">{{ $region?->name }} Region candidates (Legislative Council)</span>
                            </summary>
                            <div class="mt-3 rounded-md border border-rule-strong px-4 py-1 lg:px-6">
                                <x-candidate-list :candidates="$regionCandidates" :label="$region?->name.' Region (Legislative Council)'" />
                            </div>
                        </details>
                    @endif
                </section>
            @endif
        </div>

        <aside class="flex flex-col gap-6 lg:w-80 lg:shrink-0 lg:gap-8" aria-label="About this district">
            @if ($localities->isNotEmpty())
                <section aria-labelledby="localities" class="flex flex-col gap-2 border-t border-rule pt-3.5 lg:gap-3 lg:border-t-0 lg:pt-0">
                    <h2 id="localities" class="text-[15px] font-semibold leading-[18px] lg:eyebrow lg:border-b lg:border-rule lg:pb-3">Suburbs in this district</h2>
                    <p class="text-[13px] leading-[21px] lg:text-small lg:leading-[23px]">
                        @foreach ($localities as $locality)
                            {{ $locality->name }}@if ((float) $locality->pivot->share < 0.99) (part)@endif{{ $loop->last ? '' : ',' }}
                        @endforeach
                    </p>
                    <p class="text-[13px] leading-[19px] text-ink-muted">
                        “Part” means the suburb is split between districts. <a href="{{ config('site.vec_lookup_url') }}" class="link" rel="noopener">Check your address with the VEC <span aria-hidden="true">↗</span></a>
                    </p>
                </section>
            @endif

            <div class="flex flex-col gap-1.5 rounded-md bg-surface p-4 lg:gap-2 lg:p-5">
                <p class="text-small font-semibold leading-[18px]">Kept on this device only</p>
                <p class="text-[13px] leading-[19px] text-ink-muted lg:text-small lg:leading-[21px]">If you choose a district, we remember it in your browser so your results can show your MPs.<span class="hidden lg:inline"> We never see it.</span></p>
            </div>

            <div class="flex flex-col gap-1.5 border-t border-rule pt-4 text-small leading-[18px]">
                <p class="font-semibold">Spotted a mistake?</p>
                <p class="leading-[21px] text-ink-muted">Tell us and we'll check it against the official record.</p>
                <p><a href="{{ $reportUrl }}" class="underline underline-offset-4">Report a problem with this district <span aria-hidden="true">&rarr;</span></a></p>
            </div>
        </aside>
    </div>
</x-layouts.public>
