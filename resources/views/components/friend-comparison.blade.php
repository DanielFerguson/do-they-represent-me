{{--
    How the visitor's answers compare with a friend's, at the top of the results page. Its state is in the `results` component
    in resources/js/app.js, so it must sit inside that component's root. The friend's answers come from a link and are never
    sent anywhere.
--}}
<section x-show="hasCompare" x-cloak aria-labelledby="compare" class="flex flex-col gap-8 lg:gap-12">
    <div class="flex flex-col gap-3 lg:gap-4">
        <h2 id="compare" class="text-h1-mobile font-semibold tracking-display lg:text-h1" x-text="compareHeading"></h2>
        <p class="text-[15px] leading-[23px] text-ink-muted lg:text-[16px] lg:leading-[25px]" x-text="compareSummary"></p>

        <div x-show="compareShared > 0" class="flex flex-col gap-2.5 pt-1">
            <div class="flex gap-[3px] lg:gap-1" aria-hidden="true">
                <template x-for="segment in agreementSegments" x-bind:key="segment.key">
                    <span class="h-2 flex-1 rounded-[2px] lg:h-2.5" x-bind:class="segment.segmentClass"></span>
                </template>
            </div>
            <p class="flex gap-5 text-small leading-5 lg:gap-6">
                <span class="flex items-center gap-1.5">
                    <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" class="shrink-0"><path d="M2.5 7.5L5.5 10.5L11.5 3.5" fill="none" stroke="currentColor" stroke-width="1.75" /></svg>
                    <span class="font-semibold" x-text="compareSameCount"></span> same
                </span>
                <span class="flex items-center gap-1.5">
                    <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" class="shrink-0"><path d="M3.5 3.5L10.5 10.5M10.5 3.5L3.5 10.5" fill="none" stroke="currentColor" stroke-width="1.75" /></svg>
                    <span class="font-semibold" x-text="compareDifferentCount"></span> different
                </span>
            </p>
        </div>
    </div>

    <div x-show="compareDifferentCount > 0" class="flex flex-col border-t border-ink pt-4 lg:pt-5">
        <div class="flex items-baseline justify-between gap-4 pb-2 lg:pb-3">
            <h3 class="text-[19px] font-semibold leading-[26px] tracking-[-0.01em] lg:text-h2">Where you differ</h3>
            <p class="text-[13px] leading-4 text-ink-muted lg:text-small" x-text="differenceCountText"></p>
        </div>
        <template x-for="item in differences" x-bind:key="item.id">
            <div class="flex flex-col gap-1.5 border-t border-rule py-3.5 lg:flex-row lg:items-center lg:justify-between lg:gap-6 lg:py-4">
                <div class="flex min-w-0 flex-col gap-1">
                    <span class="eyebrow" x-text="item.topic"></span>
                    <span class="text-[15px] font-medium leading-[22px] lg:text-[16px] lg:leading-6" x-text="item.question"></span>
                </div>
                <div class="flex gap-4 text-[13px] leading-[19px] lg:w-[132px] lg:shrink-0 lg:flex-col lg:gap-0.5 lg:text-small lg:leading-5">
                    <span>You: <span class="font-semibold" x-text="item.mine"></span></span>
                    <span><span x-text="friendName"></span>: <span class="font-semibold" x-text="item.theirs"></span></span>
                </div>
            </div>
        </template>
        <details x-show="compareSameCount > 0" class="group border-y border-rule">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 py-3.5 text-[15px] font-medium lg:py-4 lg:text-[16px] [&::-webkit-details-marker]:hidden">
                <span>Where you agree <span class="font-normal text-ink-muted">· <span x-text="agreementCountText"></span></span></span>
                <svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true" class="shrink-0 text-ink-muted transition-transform group-open:rotate-180 motion-reduce:transition-none"><path d="M2.5 4L6 7.5L9.5 4" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>
            </summary>
            <template x-for="item in agreements" x-bind:key="item.id">
                <div class="flex flex-col gap-1.5 border-t border-rule py-3.5 lg:flex-row lg:items-center lg:justify-between lg:gap-6">
                    <div class="flex min-w-0 flex-col gap-1">
                        <span class="eyebrow" x-text="item.topic"></span>
                        <span class="text-[15px] font-medium leading-[22px] lg:text-[16px] lg:leading-6" x-text="item.question"></span>
                    </div>
                    <span class="text-[13px] leading-[19px] lg:w-[132px] lg:shrink-0 lg:text-small lg:leading-5">Both: <span class="font-semibold" x-text="item.mine"></span></span>
                </div>
            </template>
        </details>
    </div>

    <div x-show="partyComparison.length > 0" class="flex flex-col gap-4 border-t border-ink pt-4 lg:pt-5">
        <div class="flex flex-col gap-1">
            <h3 class="text-[19px] font-semibold leading-[26px] tracking-[-0.01em] lg:text-h2">How each of you matched the parties</h3>
            <p class="text-[13px] leading-5 text-ink-muted lg:text-small lg:leading-[21px]">Listed in your order. Each percentage is out of the questions that person answered. Every bar is drawn the same way.</p>
        </div>
        <div class="flex flex-col">
            <div class="grid grid-cols-[minmax(0,1fr)_56px_56px] items-center gap-x-2 border-b border-rule pb-2 text-[11px] font-medium uppercase leading-[14px] tracking-label text-ink-muted lg:grid-cols-[190px_1fr_1fr] lg:gap-x-6 lg:text-label">
                <span>Party</span>
                <span class="text-right lg:text-left">You</span>
                <span class="text-right lg:text-left" x-text="friendName"></span>
            </div>
            <template x-for="party in partyComparison" x-bind:key="party.code">
                <div class="grid min-h-11 grid-cols-[minmax(0,1fr)_56px_56px] items-center gap-x-2 border-b border-rule py-2 lg:min-h-12 lg:grid-cols-[190px_1fr_1fr] lg:gap-x-6">
                    <span class="flex items-center gap-2.5 text-[15px] font-medium leading-5">
                        <span class="size-2.5 shrink-0 rounded-[2px]" x-bind:class="party.swatchClass" aria-hidden="true"></span>
                        <span x-text="party.name"></span>
                    </span>
                    <span class="flex items-center justify-end gap-3 lg:justify-start">
                        <span class="hidden h-2 flex-1 rounded-full bg-rule lg:flex" aria-hidden="true"><span class="h-full rounded-full bg-party" x-bind:style="party.mineStyle"></span></span>
                        <span class="w-10 text-right text-[15px] font-semibold" x-text="party.mineText"></span>
                    </span>
                    <span class="flex items-center justify-end gap-3 lg:justify-start">
                        <span class="hidden h-2 flex-1 rounded-full bg-rule lg:flex" aria-hidden="true"><span class="h-full rounded-full bg-party" x-bind:style="party.theirsStyle"></span></span>
                        <span class="w-10 text-right text-[15px] font-semibold" x-text="party.theirsText"></span>
                    </span>
                </div>
            </template>
        </div>
        <p x-show="hasPartiesWithoutRecord" class="text-[13px] leading-5 text-ink-muted">Not enough shared votes: <span x-text="partiesWithoutRecordText"></span></p>
    </div>

    <div class="flex flex-col gap-2 border-t border-rule pt-4">
        <p class="text-[13px] leading-[19px] text-ink-muted" x-text="friendNote"></p>
        <button type="button" x-on:click="stopComparing" class="link self-start text-small text-ink-muted lg:hidden">Stop comparing</button>
    </div>
</section>
