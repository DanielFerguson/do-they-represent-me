<x-layouts.public :page-type="$isPreview ? null : 'results'" title="Your results" :noindex="$isPreview">
    <div
        x-data="results"
        data-stances-url="{{ $stancesUrl }}"
        data-quiz-url="{{ $quizUrl }}"
        data-district-url="{{ $districtUrl }}"
        data-storage-key="{{ $storageKey }}"
        data-authorisation="{{ config('site.authorisation') }}"
        class="mx-auto flex max-w-page flex-col gap-8 px-5 pb-12 pt-8 lg:pb-24 lg:pt-18"
    >
        @if ($isPreview)
            <x-preview-notice />
        @elseif ($isSample)
            <x-sample-notice />
        @elseif (config('site.beta'))
            <x-beta-notice />
        @endif

        <div class="flex flex-col gap-10 lg:flex-row lg:items-start lg:justify-between lg:gap-12">
            <div class="flex min-w-0 flex-col lg:max-w-[704px] lg:flex-1">
                <h1 class="eyebrow" x-text="eyebrow">Your results</h1>

                <div x-show="isShared && showsResults" x-cloak class="mt-4 flex flex-col gap-3 rounded-md border-2 border-ink bg-surface p-4 lg:flex-row lg:items-center lg:justify-between lg:gap-6 lg:p-5">
                    <div class="flex flex-col gap-1">
                        <p class="eyebrow">Shared with you</p>
                        <p class="text-[17px] font-semibold leading-[25px] tracking-[-0.01em]" x-text="recipientHeading"></p>
                        <p class="text-small leading-[21px] text-ink-muted">Take the quiz to see how your answers compare.</p>
                    </div>
                    <a x-bind:href="compareInviteUrl" x-on:click="trackCompareCta" class="flex h-12 items-center justify-center rounded-md bg-ink px-5 text-[15px] font-medium leading-[18px] text-ground hover:bg-ink/85 lg:shrink-0">Take the quiz and compare</a>
                </div>

                <noscript>
                    <p class="mt-4 text-ink-muted">Your results are worked out in your browser, which needs JavaScript. You can still read <a href="{{ route('policies.index') }}" class="link text-ink">every question and the votes behind it</a>, and <a href="{{ route('districts.index') }}" class="link text-ink">how your MPs voted</a>.</p>
                </noscript>

                <p x-show="loading" role="status" class="mt-4 text-ink-muted">Working out your results…</p>
                <p x-show="failed" x-cloak role="alert" class="mt-4 text-ink-muted">Sorry, the results couldn't be loaded. Please refresh the page to try again.</p>

                <template x-if="showsTooFew">
                    <div class="mt-5 flex flex-col gap-5">
                        <h2 class="text-h1-mobile font-semibold tracking-display lg:text-h1">Answer a few more questions</h2>
                        <p class="text-[16px] leading-[25px] text-ink-muted">
                            We need at least <span x-text="minimumAnswers"></span> agree or disagree answers to compare you fairly with each party. You've given <span x-text="comparable"></span>.
                        </p>
                        <div class="flex flex-col gap-2">
                            <div class="flex gap-1" aria-hidden="true">
                                <template x-for="segment in answerSegments" x-bind:key="segment.key">
                                    <span class="h-1.5 flex-1 rounded-[2px]" x-bind:class="segment.segmentClass"></span>
                                </template>
                            </div>
                            <p class="text-[13px] leading-4 text-ink-muted" x-text="neededText"></p>
                        </div>
                        <a x-bind:href="changeAnswersUrl" class="flex h-12 items-center justify-center rounded-md bg-ink px-8 text-[15px] font-medium leading-[18px] text-ground hover:bg-ink/85 lg:self-start">Back to the questions</a>
                    </div>
                </template>

                <template x-if="showsResults">
                    <div class="mt-3 flex flex-col gap-10 lg:mt-4 lg:gap-14">
                        <x-friend-comparison />

                        <section aria-labelledby="parties" class="flex flex-col gap-10 lg:gap-14">
                            <div class="flex flex-col gap-3 lg:gap-4">
                                <h2 id="parties" class="text-h1-mobile font-semibold tracking-display lg:text-h1" x-text="partiesHeading">How often each party voted the way you would have</h2>
                                <p class="text-[15px] leading-[23px] text-ink-muted lg:text-[16px] lg:leading-[25px]">
                                    <span x-text="basedOnText"></span>
                                    <span x-show="dataAsOf">Voting records up to <span x-text="dataAsOf"></span>.</span>
                                    <span class="hidden lg:inline" x-show="!isShared">Parties are listed by how often they matched you, all drawn the same way.</span>
                                </p>
                                <p class="text-[15px] leading-[23px] text-ink lg:text-[16px] lg:leading-[25px]" x-text="scopeText">These results compare your answers with how parties voted in the 60th Parliament (2022–2026), not with their promises for the 2026 election.</p>
                            </div>

                            <div class="flex flex-col border-t border-ink">
                                <div class="hidden h-10 items-center gap-6 border-b border-rule lg:flex" aria-hidden="true">
                                    <span class="eyebrow w-[180px] shrink-0">Party</span>
                                    <span class="flex flex-1 justify-between text-label text-ink-muted"><span>0%</span><span>50%</span><span>100%</span></span>
                                    <span class="eyebrow w-[144px] shrink-0 text-right" x-text="matchedLabel">Matched you</span>
                                </div>

                                <ol>
                                    <template x-for="party in rankedParties" x-bind:key="party.code">
                                        <li class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-2.5 border-b border-rule py-3.5 lg:grid-cols-[180px_minmax(0,1fr)_144px] lg:gap-x-6 lg:gap-y-0.5 lg:py-5">
                                            <span class="sr-only" x-text="party.label"></span>
                                            <span class="col-start-1 row-start-1 flex items-center gap-2.5" aria-hidden="true">
                                                <span class="size-2.5 shrink-0 rounded-[2px]" x-bind:class="party.swatchClass"></span>
                                                <span class="text-[16px] font-medium leading-5" x-text="party.short_name"></span>
                                            </span>
                                            <span class="col-start-2 row-start-1 flex items-baseline justify-end gap-2 lg:col-start-3 lg:row-span-2 lg:gap-3" aria-hidden="true">
                                                <span class="w-12 shrink-0 text-right text-body font-semibold leading-[22px] lg:w-[52px] lg:text-[18px]" x-text="party.percentText"></span>
                                                <span class="w-20 shrink-0 whitespace-nowrap text-right text-[13px] leading-4 text-ink-muted" x-text="party.sharedText"></span>
                                            </span>
                                            <span class="col-span-2 row-start-2 flex h-1.5 rounded-full bg-rule lg:col-span-1 lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:h-2" aria-hidden="true">
                                                <span class="h-full rounded-full bg-party" x-bind:style="party.barStyle"></span>
                                            </span>
                                            <a x-show="party.notCounted" href="{{ route('methodology') }}#not-counted" class="link col-span-2 row-start-3 justify-self-start text-label text-ink-muted lg:col-span-1 lg:col-start-1 lg:row-start-2 lg:pl-5">
                                                <span x-text="party.notCountedCount"></span> <span class="lg:hidden" x-text="party.notCountedNoun"></span> not counted · why?<span class="sr-only"> (<span x-text="party.short_name"></span>)</span>
                                            </a>
                                        </li>
                                    </template>
                                </ol>

                                <p x-show="hasPartiesWithoutRecord" class="pt-3 text-[13px] leading-5 text-ink-muted lg:flex lg:gap-6 lg:pt-4 lg:text-small lg:leading-[21px]">
                                    <span class="lg:w-[180px] lg:shrink-0">Not enough shared votes<span class="lg:hidden">:</span></span>
                                    <span class="lg:flex-1 lg:text-ink"><span x-text="partiesWithoutRecordText"></span> Independent MPs are compared one by one on each district page.</span>
                                </p>
                            </div>
                        </section>

                        <div class="flex flex-col gap-2.5 lg:hidden">
                            @if ($isPreview)
                                <button type="button" x-on:click="copyLink" class="flex h-12 items-center justify-center rounded-md bg-ink px-4 text-[15px] font-medium leading-[18px] text-ground hover:bg-ink/85">Copy a link to these results</button>
                            @else
                                <button type="button" x-show="!isShared" x-on:click="openShare" class="flex h-12 items-center justify-center gap-2 rounded-md bg-ink px-4 text-[15px] font-medium leading-[18px] text-ground hover:bg-ink/85"><svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" class="shrink-0"><path d="M8 10.5V2M8 2L4.75 5.25M8 2l3.25 3.25M3 9v4.25c0 .414.336.75.75.75h8.5a.75.75 0 0 0 .75-.75V9" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>Share</button>
                            @endif
                            @unless ($isPreview)
                                <button type="button" x-show="isShared" x-cloak x-on:click="copyLink" class="flex h-12 items-center justify-center rounded-md bg-ink px-4 text-[15px] font-medium leading-[18px] text-ground hover:bg-ink/85">Copy a link to these results</button>
                            @endunless
                            <a x-show="!isShared" x-bind:href="changeAnswersUrl" class="flex h-12 items-center justify-center rounded-md border border-rule-strong px-4 text-[15px] font-medium leading-[18px] hover:border-ink">Change my answers</a>
                            @if ($isPreview)
                                <p role="status" class="text-small text-ink-muted" x-text="copyStatus"></p>
                                <p class="text-[13px] leading-[19px] text-ink-muted">The link keeps your answers after the #, which browsers never send to a server.</p>
                            @else
                                <p role="status" x-show="isShared" x-cloak class="text-small text-ink-muted" x-text="copyStatus"></p>
                                <p x-show="!isShared" class="text-[13px] leading-[19px] text-ink-muted">Share a link, or an image of these results. Nothing is sent to us.</p>
                                <p x-show="isShared" x-cloak class="text-[13px] leading-[19px] text-ink-muted">The answers are in the link, after the #, which browsers never send to a server.</p>
                            @endif
                        </div>

                        <section x-show="hasMembers" aria-labelledby="representatives" class="flex flex-col gap-5 border-t border-ink pt-4 lg:pt-5">
                            <div class="flex flex-col gap-2">
                                <h2 id="representatives" class="text-[19px] font-semibold leading-[26px] tracking-[-0.01em] lg:text-h2">Your members of Parliament</h2>
                                <p class="text-small leading-[21px] text-ink-muted">
                                    How often your MLA and your region's five MLCs voted the way you would have, on the questions they voted on. MPs mostly vote with their party.
                                </p>
                            </div>

                            <div class="flex flex-col gap-2">
                                <label for="district" class="text-small font-medium">Your district</label>
                                <select id="district" x-on:change="chooseDistrict" class="h-12 w-full max-w-sm rounded-md border border-rule-strong bg-ground px-3 text-[16px] hover:border-ink">
                                    <option value="" x-bind:selected="!district">Choose your district…</option>
                                    <template x-for="option in districts" x-bind:key="option.slug">
                                        <option x-bind:value="option.slug" x-bind:selected="isDistrict(option.slug)" x-text="option.name"></option>
                                    </template>
                                </select>
                                <p class="text-[13px] leading-[19px] text-ink-muted">Not sure? <a href="{{ route('districts.index') }}" class="link text-ink">Find it by suburb</a>. Your district stays in your browser, like your answers.</p>
                                <p class="sr-only" aria-live="polite" x-text="districtAnnouncement"></p>
                            </div>

                            <template x-if="representatives">
                                <div class="flex flex-col gap-4">
                                    <p x-show="isVacant" class="text-small text-ink-muted"><span x-text="districtName"></span> has no member of the Legislative Assembly at present.</p>
                                    <ol class="border-t border-rule">
                                        <template x-for="member in representativeRows" x-bind:key="member.slug">
                                            <li class="flex flex-col gap-2.5 border-b border-rule py-3.5">
                                                <span class="sr-only" x-text="member.label"></span>
                                                <span class="flex items-start justify-between gap-4" aria-hidden="true">
                                                    <span class="flex min-w-0 flex-col gap-0.5">
                                                        <span class="flex items-center gap-2.5">
                                                            <span class="size-2.5 shrink-0 rounded-[2px]" x-bind:class="member.swatchClass"></span>
                                                            <span class="text-[16px] font-medium leading-5" x-text="member.name"></span>
                                                        </span>
                                                        <span class="pl-5 text-[13px] leading-[19px] text-ink-muted"><span x-text="member.party"></span> · <span x-text="member.role"></span></span>
                                                    </span>
                                                    <span x-show="member.hasScore" class="flex shrink-0 items-baseline gap-2 lg:gap-3">
                                                        <span class="w-12 text-right text-body font-semibold leading-[22px] lg:w-[52px] lg:text-[18px]" x-text="member.percentText"></span>
                                                        <span class="w-20 whitespace-nowrap text-right text-[13px] leading-4 text-ink-muted" x-text="member.sharedText"></span>
                                                    </span>
                                                    <span x-show="!member.hasScore" class="shrink-0 text-right text-[13px] leading-[19px] text-ink-muted">Too few shared votes to compare</span>
                                                </span>
                                                <span x-show="member.hasScore" class="flex h-1.5 rounded-full bg-rule lg:h-2" aria-hidden="true">
                                                    <span class="h-full rounded-full bg-party" x-bind:style="member.barStyle"></span>
                                                </span>
                                            </li>
                                        </template>
                                    </ol>
                                    <p class="text-small"><a x-bind:href="districtPageUrl" class="link">See how they voted, question by question &rarr;</a></p>
                                </div>
                            </template>
                        </section>

                        <section aria-labelledby="answers" class="flex flex-col border-t border-ink pt-4 lg:pt-5">
                            <div class="flex items-baseline justify-between gap-4 pb-2 lg:pb-5">
                                <h2 id="answers" class="text-[19px] font-semibold leading-[26px] tracking-[-0.01em] lg:text-h2">Question by question</h2>
                                <p class="hidden text-small leading-[18px] text-ink-muted lg:block">Open any question to see how each party voted</p>
                            </div>

                            <template x-for="policy in answeredPolicies" x-bind:key="policy.id">
                                <details class="group border-t border-rule">
                                    <summary class="flex cursor-pointer list-none flex-col gap-1.5 py-3.5 lg:flex-row lg:items-center lg:gap-6 lg:py-4 [&::-webkit-details-marker]:hidden">
                                        <span class="flex min-w-0 flex-col gap-1.5 lg:flex-1 lg:gap-1">
                                            <span class="eyebrow" x-text="policy.topic"></span>
                                            <span class="text-[15px] font-medium leading-[22px] group-hover:underline group-hover:underline-offset-4 lg:text-[16px] lg:leading-6" x-text="policy.question"></span>
                                        </span>
                                        <span class="flex items-center justify-between gap-6 lg:shrink-0">
                                            <span class="text-[13px] leading-4 text-ink-muted lg:w-[112px] lg:text-right lg:text-small lg:leading-[18px] lg:text-ink lg:group-open:text-ink-muted"><span x-text="answerWho"></span>: <span x-text="policy.yourAnswer"></span></span>
                                            <svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true" class="shrink-0 text-ink-muted transition-transform group-open:rotate-180 group-open:text-ink motion-reduce:transition-none"><path d="M2.5 4L6 7.5L9.5 4" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>
                                        </span>
                                    </summary>

                                    <div class="mb-3.5 rounded-md bg-surface px-4 py-1 lg:mb-6 lg:px-5 lg:py-2">
                                        <table class="block w-full text-small lg:table">
                                            <caption class="sr-only" x-text="policy.caption"></caption>
                                            <thead class="block lg:table-header-group">
                                                <tr class="sr-only lg:not-sr-only lg:table-row">
                                                    <th scope="col" class="eyebrow h-9 w-[180px] pr-4 text-left align-middle">Party</th>
                                                    <th scope="col" class="eyebrow h-9 pr-4 text-left align-middle">How they voted</th>
                                                    <th scope="col" class="eyebrow h-9 w-[120px] text-right align-middle">Vs you</th>
                                                </tr>
                                            </thead>
                                            <tbody class="block lg:table-row-group">
                                                <template x-for="party in policy.parties" x-bind:key="party.code">
                                                    <tr class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-4 gap-y-0.5 border-t border-rule py-2.5 first:border-t-0 lg:table-row lg:first:border-t">
                                                        <th scope="row" class="col-start-1 row-start-1 text-left font-medium lg:py-2.5 lg:pr-4 lg:align-top lg:font-normal">
                                                            <span class="flex items-center gap-2 lg:gap-2.5">
                                                                <span aria-hidden="true" class="size-2 shrink-0 rounded-[2px]" x-bind:class="party.swatchClass"></span>
                                                                <span x-text="party.name"></span>
                                                            </span>
                                                        </th>
                                                        <td class="col-span-2 row-start-2 pl-4 text-[13px] leading-[19px] text-ink-muted lg:py-2.5 lg:pl-0 lg:pr-4 lg:align-top lg:text-small" x-bind:class="party.stanceClass" x-text="party.stance"></td>
                                                        <td class="col-start-2 row-start-1 text-right lg:py-2.5 lg:align-top">
                                                            <span x-show="party.isSame" class="inline-flex items-center gap-1.5 font-medium">
                                                                <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" class="shrink-0"><path d="M2.5 7.5L5.5 10.5L11.5 3.5" fill="none" stroke="currentColor" stroke-width="1.75" /></svg>
                                                                Same
                                                            </span>
                                                            <span x-show="party.isDifferent" class="inline-flex items-center gap-1.5 font-medium">
                                                                <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" class="shrink-0"><path d="M3.5 3.5L10.5 10.5M10.5 3.5L3.5 10.5" fill="none" stroke="currentColor" stroke-width="1.75" /></svg>
                                                                Different
                                                            </span>
                                                            <span x-show="party.isNotCounted" class="whitespace-nowrap text-ink-muted"><span aria-hidden="true">— </span>Not counted</span>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                        <div x-show="policy.url" class="flex min-h-11 items-center justify-between gap-4 border-t border-rule py-2 text-small">
                                            <a x-bind:href="policy.url" class="link text-ink">See the votes and what each side said &rarr;<span class="sr-only">: <span x-text="policy.title"></span></span></a>
                                            <a href="{{ route('contact') }}" class="link hidden text-[13px] leading-4 text-ink-muted lg:inline">Report a problem</a>
                                        </div>
                                    </div>
                                </details>
                            </template>
                        </section>
                    </div>
                </template>
            </div>

            <template x-if="showsResults">
                <aside class="hidden w-80 shrink-0 flex-col gap-8 lg:flex" aria-label="Your answers and sharing">
                    <div class="flex flex-col">
                        <h2 class="eyebrow border-b border-rule pb-3" x-text="answersHeading">Your answers</h2>
                        <dl class="text-small leading-[18px]">
                            <div class="flex h-10 items-center justify-between border-b border-rule">
                                <dt>Agree or disagree</dt>
                                <dd class="font-semibold" x-text="comparable"></dd>
                            </div>
                            <div class="flex h-10 items-center justify-between border-b border-rule text-ink-muted">
                                <dt>Unsure (not counted)</dt>
                                <dd x-text="unsureCount"></dd>
                            </div>
                            <div class="flex h-10 items-center justify-between border-b border-rule text-ink-muted">
                                <dt>Skipped</dt>
                                <dd x-text="skippedCount"></dd>
                            </div>
                        </dl>
                    </div>

                    <div x-show="hasCompare" x-cloak class="flex flex-col">
                        <h2 class="eyebrow border-b border-rule pb-3" x-text="comparingWithLabel"></h2>
                        <dl class="text-small leading-[18px]">
                            <div class="flex h-10 items-center justify-between border-b border-rule">
                                <dt>You both answered</dt>
                                <dd class="font-semibold" x-text="compareShared"></dd>
                            </div>
                            <div class="flex h-10 items-center justify-between border-b border-rule text-ink-muted">
                                <dt>Only you answered</dt>
                                <dd x-text="onlyMineCount"></dd>
                            </div>
                            <div class="flex h-10 items-center justify-between border-b border-rule text-ink-muted">
                                <dt x-text="onlyTheirsLabel"></dt>
                                <dd x-text="onlyTheirsCount"></dd>
                            </div>
                        </dl>
                        <button type="button" x-on:click="stopComparing" class="link self-start pt-3 text-small text-ink-muted">Stop comparing</button>
                    </div>

                    <div class="flex flex-col gap-3">
                        @if ($isPreview)
                            <button type="button" x-on:click="copyLink" class="flex h-12 items-center justify-center rounded-md bg-ink px-4 text-[15px] font-medium leading-[18px] text-ground hover:bg-ink/85">Copy a link to these results</button>
                        @else
                            <button type="button" x-show="!isShared" x-on:click="openShare" class="flex h-12 items-center justify-center gap-2 rounded-md bg-ink px-4 text-[15px] font-medium leading-[18px] text-ground hover:bg-ink/85"><svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" class="shrink-0"><path d="M8 10.5V2M8 2L4.75 5.25M8 2l3.25 3.25M3 9v4.25c0 .414.336.75.75.75h8.5a.75.75 0 0 0 .75-.75V9" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>Share</button>
                        @endif
                        @unless ($isPreview)
                            <button type="button" x-show="isShared" x-cloak x-on:click="copyLink" class="flex h-12 items-center justify-center rounded-md bg-ink px-4 text-[15px] font-medium leading-[18px] text-ground hover:bg-ink/85">Copy a link to these results</button>
                        @endunless
                        <a x-show="!isShared" x-bind:href="changeAnswersUrl" class="flex h-12 items-center justify-center rounded-md border border-rule-strong px-4 text-[15px] font-medium leading-[18px] hover:border-ink">Change my answers</a>
                        @if ($isPreview)
                            <p role="status" class="text-small text-ink-muted" x-text="copyStatus"></p>
                            <p class="text-[13px] leading-[19px] text-ink-muted">The link keeps your answers after the #, which browsers never send to a server.</p>
                        @else
                            <p role="status" x-show="isShared" x-cloak class="text-small text-ink-muted" x-text="copyStatus"></p>
                            <p x-show="!isShared" class="text-[13px] leading-[19px] text-ink-muted">Share a link, or an image of these results. Nothing is sent to us.</p>
                            <p x-show="isShared" x-cloak class="text-[13px] leading-[19px] text-ink-muted">The answers are in the link, after the #, which browsers never send to a server.</p>
                        @endif
                    </div>

                    <div class="flex flex-col gap-1.5 border-t border-rule pt-4 text-small">
                        <h2 class="font-semibold leading-[18px]">How is this worked out?</h2>
                        <p class="leading-[21px] text-ink-muted">Each party's position comes from how its MPs voted on the linked divisions. Absences aren't counted.</p>
                        <p class="leading-[18px]"><a href="{{ route('methodology') }}" class="link">Read the methodology &rarr;</a></p>
                    </div>
                </aside>
            </template>
        </div>

        @unless ($isPreview)
            <x-share-sheet />
        @endunless
    </div>
</x-layouts.public>
