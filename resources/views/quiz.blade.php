<x-layouts.public :title="$isPreview ? 'Quiz' : null" :noindex="$isPreview">
    <div
        x-data="quiz"
        data-stances-url="{{ $stancesUrl }}"
        data-results-url="{{ $resultsUrl }}"
        data-storage-key="{{ $storageKey }}"
        class="mx-auto flex max-w-page flex-col gap-8 px-5 pb-12 pt-8 lg:pb-24 lg:pt-18"
    >
        @if ($isPreview)
            <x-preview-notice />
        @elseif ($isSample)
            <x-sample-notice />
        @elseif (config('site.beta'))
            <x-beta-notice />
        @endif

        <div class="flex flex-col gap-24 lg:flex-row lg:items-start">
            <div class="flex min-w-0 flex-col gap-10 lg:w-[704px] lg:shrink-0">
                {{-- The intro shrinks to a title line once any answer is saved, so returning visitors start on their question. --}}
                <div class="flex items-baseline justify-between gap-4">
                    <div class="flex flex-col gap-3.5 lg:gap-5">
                        <p x-show="!hasStarted" class="eyebrow">State election · Saturday 28 November</p>
                        <h1 x-bind:class="titleClass" class="text-[32px] font-semibold leading-9 tracking-display lg:text-display">How do Victoria's parties actually vote?</h1>
                        <p x-show="!hasStarted" class="text-body leading-[25px] text-ink-muted lg:max-w-[600px] lg:text-[19px] lg:leading-[30px]">
                            Answer questions on issues State Parliament has voted on since 2022, then see which parties voted the way you would have. The record, not the rhetoric.
                        </p>
                        <p x-show="showsQuestionCount" x-cloak class="text-[13px] leading-5 text-ink-muted lg:hidden"><span x-text="total"></span> questions · about 5 minutes · your answers stay in your browser</p>
                    </div>
                    <button type="button" x-show="hasStarted" x-cloak x-on:click="startAgain" class="link shrink-0 text-small text-ink-muted">Start again</button>
                </div>

                <noscript>
                    <p class="text-ink-muted">The quiz needs JavaScript. You can still read <a href="{{ route('policies.index') }}" class="link text-ink">every question and the votes behind it</a>, and <a href="{{ route('districts.index') }}" class="link text-ink">how your MPs voted</a>.</p>
                </noscript>

                <p x-show="loading" role="status" class="text-ink-muted">Loading questions…</p>
                <p x-show="failed" x-cloak role="alert" class="text-ink-muted">Sorry, the questions couldn't be loaded. Please refresh the page to try again.</p>

                <template x-if="current">
                    <section
                        tabindex="-1"
                        aria-labelledby="question"
                        x-on:keydown="onKey"
                        class="flex flex-col gap-6 border-t border-ink pt-6 lg:gap-8"
                    >
                        <div class="flex flex-col gap-3">
                            <div class="flex items-baseline justify-between gap-4">
                                <span class="eyebrow" x-text="current.topic"></span>
                                <span class="text-small font-medium text-ink-muted" x-text="positionShort"></span>
                            </div>
                            <div class="h-0.5 bg-rule" aria-hidden="true">
                                <div class="h-0.5 bg-ink transition-[width] motion-reduce:transition-none" x-bind:style="progress"></div>
                            </div>
                        </div>

                        <h2 id="question" class="text-[24px] font-semibold leading-[31px] tracking-tight lg:text-[32px] lg:leading-10" x-text="current.question"></h2>

                        <details x-ref="about" x-show="current.description || current.agree_means || current.url" class="group">
                            <summary class="flex cursor-pointer list-none items-center gap-2 text-small text-ink-muted group-open:font-medium group-open:text-ink [&::-webkit-details-marker]:hidden">
                                <svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true" class="shrink-0 transition-transform group-open:rotate-90 motion-reduce:transition-none"><path d="M4 2.5L7.5 6L4 9.5" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>
                                <span class="link group-open:no-underline">About this question and what “agree” means</span>
                            </summary>
                            <div class="mt-3 flex flex-col gap-2.5 border-l-2 border-rule py-0.5 pl-5 text-[15px] leading-6">
                                <p x-show="current.description" class="text-ink-muted" x-text="current.description"></p>
                                <p x-show="current.agree_means" class="text-ink"><span class="font-medium">Agree</span> = <span x-text="current.agree_means"></span></p>
                                <p x-show="current.url"><a x-bind:href="current.url" class="link text-small text-ink-muted">See the votes behind this question</a></p>
                            </div>
                        </details>

                        <div class="flex flex-col gap-3">
                            <div class="grid grid-cols-2 gap-3">
                                <button type="button" x-on:click="agree" x-bind:aria-pressed="isSelected('a')" class="group flex h-14 items-center justify-center rounded-md border border-rule-strong px-4 text-[18px] font-medium hover:border-ink aria-pressed:border-ink aria-pressed:bg-ink aria-pressed:text-ground lg:h-16 lg:justify-between lg:px-6">
                                    Agree
                                    <kbd aria-hidden="true" class="hidden rounded-sm border border-rule px-1.5 py-0.5 font-sans text-label font-medium text-ink-muted group-aria-pressed:border-ground/35 group-aria-pressed:text-ground lg:inline-block">A</kbd>
                                </button>
                                <button type="button" x-on:click="disagree" x-bind:aria-pressed="isSelected('d')" class="group flex h-14 items-center justify-center rounded-md border border-rule-strong px-4 text-[18px] font-medium hover:border-ink aria-pressed:border-ink aria-pressed:bg-ink aria-pressed:text-ground lg:h-16 lg:justify-between lg:px-6">
                                    Disagree
                                    <kbd aria-hidden="true" class="hidden rounded-sm border border-rule px-1.5 py-0.5 font-sans text-label font-medium text-ink-muted group-aria-pressed:border-ground/35 group-aria-pressed:text-ground lg:inline-block">D</kbd>
                                </button>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <button type="button" x-on:click="unsure" x-bind:aria-pressed="isSelected('u')" class="group flex h-12 items-center justify-center rounded-md border border-rule px-4 text-[15px] hover:border-ink aria-pressed:border-ink aria-pressed:bg-ink aria-pressed:text-ground lg:justify-between lg:px-6">
                                    Unsure
                                    <kbd aria-hidden="true" class="hidden rounded-sm border border-rule px-1.5 py-0.5 font-sans text-label font-medium text-ink-muted group-aria-pressed:border-ground/35 group-aria-pressed:text-ground lg:inline-block">U</kbd>
                                </button>
                                <button type="button" x-on:click="skip" class="flex h-12 items-center justify-center rounded-md border border-transparent px-4 text-[15px] text-ink-muted hover:text-ink lg:justify-between lg:px-6">
                                    Skip for now
                                    <kbd aria-hidden="true" class="hidden rounded-sm border border-rule px-1.5 py-0.5 font-sans text-label font-medium text-ink-muted lg:inline-block">S</kbd>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-4 border-t border-rule pt-5 text-small">
                            <button type="button" x-on:click="back" x-bind:aria-disabled="isFirst" class="py-1 text-ink hover:underline hover:underline-offset-4 aria-disabled:cursor-default aria-disabled:text-rule-strong aria-disabled:hover:no-underline">&larr; Previous</button>
                            <button type="button" x-show="canSeeResults" x-on:click="finish" class="py-1 font-medium hover:underline hover:underline-offset-4">See my results &rarr;</button>
                            <span x-show="!canSeeResults" class="text-right text-ink-muted">Answer <span x-text="remainingForResults"></span> more to see your results</span>
                        </div>

                        <p class="hidden text-label text-ink-muted lg:block">
                            Keyboard: once you've clicked an answer, press <kbd>A</kbd> agree, <kbd>D</kbd> disagree, <kbd>U</kbd> unsure, <kbd>S</kbd> skip, <kbd>&larr;</kbd> previous.
                        </p>
                    </section>
                </template>

                <p class="sr-only" aria-live="polite" x-text="announcement"></p>
            </div>

            <aside class="hidden w-80 shrink-0 flex-col gap-8 lg:flex" aria-label="About the quiz">
                <div x-show="!hasStarted" class="flex flex-col gap-4">
                    <h2 class="eyebrow">How it works</h2>
                    <ol class="flex flex-col gap-4 text-small leading-[21px] text-ink-muted">
                        <li class="flex gap-4 border-t border-rule pt-3"><span class="w-5 shrink-0 font-semibold text-ink">1</span> Every question is linked to real votes in the Legislative Assembly and Council.</li>
                        <li class="flex gap-4 border-t border-rule pt-3"><span class="w-5 shrink-0 font-semibold text-ink">2</span> Agree, disagree or say you're unsure. Skip anything you like.</li>
                        <li class="flex gap-4 border-t border-rule pt-3"><span class="w-5 shrink-0 font-semibold text-ink">3</span> <span>See how often each party voted your way, with <a href="{{ route('policies.index') }}" class="link text-ink">the evidence behind every answer</a>.</span></li>
                    </ol>
                </div>
                <div x-show="!hasStarted" class="flex flex-col gap-2 rounded-md bg-surface p-5 text-small leading-[21px]">
                    <p class="font-semibold">Your answers stay in your browser</p>
                    <p class="text-ink-muted">They're never sent to us. No accounts, no tracking. About 5 minutes.</p>
                </div>

                <div x-show="hasStarted" x-cloak class="flex flex-col">
                    <div class="flex items-baseline justify-between border-b border-rule pb-3">
                        <h2 class="eyebrow">Your answers</h2>
                        <span class="text-label text-ink-muted"><span x-text="answeredCount"></span> of <span x-text="total"></span></span>
                    </div>
                    <ol>
                        <template x-for="item in railItems" x-bind:key="item.id">
                            <li>
                                <button type="button" x-on:click="goTo(item.index)" x-bind:aria-current="item.current" class="group flex h-10 w-full items-center gap-3 border-b border-rule text-left">
                                    <span class="w-5 shrink-0 text-[13px] leading-4 text-ink-muted group-aria-[current=step]:font-semibold group-aria-[current=step]:text-ink" x-text="item.number"></span>
                                    <span class="min-w-0 flex-1 truncate text-small group-hover:underline group-hover:underline-offset-4 group-aria-[current=step]:font-semibold" x-bind:class="item.titleClass" x-text="item.title"></span>
                                    <span class="w-18 shrink-0 text-right text-[13px] font-medium leading-4" x-bind:class="item.answerClass" x-text="item.answer"></span>
                                </button>
                            </li>
                        </template>
                    </ol>
                    <p x-show="hasMoreInRail" class="flex h-10 items-center text-[13px] text-ink-muted" x-text="railMoreLabel"></p>
                </div>
            </aside>
        </div>
    </div>
</x-layouts.public>
