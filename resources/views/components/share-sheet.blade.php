{{--
    The share sheet on the results page, opened by the Share buttons. A native <dialog>, so the browser traps focus, closes it on Escape and returns focus to the button.
    On a narrow screen it is a bottom sheet in two steps (choose what to share, then share it). On a wide screen it is one dialog with everything showing.
    Its state and methods are in the `results` component in resources/js/app.js, so it must sit inside that component's root.
--}}
<dialog
    x-ref="sheet"
    x-on:click="onSheetClick"
    x-on:close="onSheetClosed"
    aria-labelledby="share-title"
    class="m-0 mt-auto max-h-[92dvh] w-full max-w-none overflow-y-auto rounded-t-2xl bg-ground p-0 text-ink backdrop:bg-ink/50 lg:m-auto lg:max-h-[calc(100dvh-4rem)] lg:w-190 lg:max-w-[calc(100vw-2rem)] lg:rounded-lg"
>
    <div class="flex flex-col gap-5 px-5 pb-7 pt-3 lg:px-8 lg:pb-8 lg:pt-7">
        <div class="flex justify-center lg:hidden" aria-hidden="true"><span class="h-1 w-9 rounded-full bg-rule-strong"></span></div>

        <div class="flex items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-2.5">
                <button type="button" x-show="showsBack" x-cloak x-on:click="backShare" aria-label="Back" class="flex size-9 shrink-0 items-center justify-center rounded-md border border-rule-strong hover:border-ink">
                    <svg width="14" height="12" viewBox="0 0 14 12" aria-hidden="true"><path d="M13 6H1.5M6 1.5L1.5 6L6 10.5" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>
                </button>
                <h2 id="share-title" class="text-h2 font-semibold tracking-tight lg:text-[24px] lg:leading-[30px]" x-text="sheetTitle">Share</h2>
            </div>
            <button type="button" x-on:click="closeShare" aria-label="Close" class="flex size-9 shrink-0 items-center justify-center rounded-md border border-rule-strong hover:border-ink">
                <svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true"><path d="M2 2l8 8M10 2l-8 8" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>
            </button>
        </div>

        <div class="flex flex-col gap-5 lg:grid lg:items-start lg:gap-x-8" x-bind:class="bodyClass">
            <fieldset x-show="showsChoice" class="m-0 flex min-w-0 flex-col gap-2.5 border-0 p-0 lg:col-span-full lg:flex-row lg:gap-4">
                <legend class="sr-only">What to share</legend>
                <label class="flex flex-1 cursor-pointer gap-3.5 rounded-md border-2 border-rule-strong p-4 hover:border-ink has-checked:border-ink has-checked:bg-surface has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ink">
                    <input type="radio" name="share-kind" value="results" x-model="shareKind" x-on:change="onKindChange" class="mt-0.5 size-5 shrink-0 accent-ink">
                    <span class="flex flex-col gap-1">
                        <span class="text-[16px] font-semibold leading-[22px]">My results</span>
                        <span class="text-small text-ink-muted">A link with my answers in it, and an image of the results. Anyone with the link can see my answers.</span>
                    </span>
                </label>
                <label class="flex flex-1 cursor-pointer gap-3.5 rounded-md border-2 border-rule-strong p-4 hover:border-ink has-checked:border-ink has-checked:bg-surface has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ink">
                    <input type="radio" name="share-kind" value="invite" x-model="shareKind" x-on:change="onKindChange" class="mt-0.5 size-5 shrink-0 accent-ink">
                    <span class="flex flex-col gap-1">
                        <span class="text-[16px] font-semibold leading-[22px]">Invite someone</span>
                        <span class="text-small text-ink-muted">A link to the quiz, with none of my answers. They take it themselves.</span>
                    </span>
                </label>
            </fieldset>

            <div x-show="showsCard" x-cloak class="flex flex-col gap-2.5 lg:col-start-1 lg:row-span-2 lg:row-start-2">
                <div class="flex items-start gap-4 lg:block">
                    <div class="relative aspect-[1080/1350] w-38 shrink-0 overflow-hidden rounded-sm border border-rule-strong bg-white lg:w-full">
                        <img x-show="card" x-cloak x-bind:src="cardSrc" alt="Your results as a picture: how often each party voted the way you would have." class="block size-full">
                        <p x-show="!card && !cardFailed" role="status" class="absolute inset-0 flex items-center justify-center p-3 text-center text-small text-[#525252]">Making your image…</p>
                        <p x-show="cardFailed" x-cloak role="alert" class="absolute inset-0 flex items-center justify-center p-3 text-center text-small text-[#525252]">Sorry, the image couldn't be made. You can still share the link.</p>
                    </div>
                    <div class="flex flex-col gap-1.5 lg:hidden">
                        <p class="text-small font-semibold">Image of my results</p>
                        <p class="text-[13px] leading-[19px] text-ink-muted">Made on your device and never uploaded. Shows every party, the way the results page does.</p>
                    </div>
                </div>
                <p class="hidden text-label leading-[17px] text-ink-muted lg:block">Image preview. Made on your device, never uploaded. Every party is shown, as on this page.</p>
            </div>

            <div class="flex min-w-0 flex-col gap-5 lg:col-start-2 lg:row-start-2 lg:row-span-2" x-bind:class="isResultsKind ? '' : 'lg:col-span-full'">
                <div x-show="showsChoice" class="flex flex-col gap-1.5">
                    <label for="share-name" class="text-small font-medium">Your name <span class="font-normal text-ink-muted">(optional)</span></label>
                    <input id="share-name" type="text" maxlength="24" autocomplete="nickname" x-model="shareName" class="h-12 w-full rounded-md border border-rule-strong bg-ground px-3 text-[16px] hover:border-ink lg:h-11">
                    <p class="text-[13px] leading-[19px] text-ink-muted" x-text="nameHint"></p>
                </div>

                <button type="button" x-show="showsContinue" x-on:click="continueShare" class="flex h-12 items-center justify-center rounded-md bg-ink px-4 text-[15px] font-medium leading-[18px] text-ground hover:bg-ink/85">Continue</button>

                <div x-show="showsShare" x-cloak class="flex flex-col gap-5">
                    <button type="button" x-show="canNative" x-cloak x-on:click="nativeShare" class="flex h-12 items-center justify-center gap-2 rounded-md bg-ink px-4 text-[15px] font-medium leading-[18px] text-ground hover:bg-ink/85">
                        <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" class="shrink-0"><path d="M8 10.5V2M8 2L4.75 5.25M8 2l3.25 3.25M3 9v4.25c0 .414.336.75.75.75h8.5a.75.75 0 0 0 .75-.75V9" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        <span x-text="nativeLabel"></span>
                    </button>

                    <button type="button" x-show="isResultsKind" x-cloak x-on:click="saveImage" x-bind:disabled="!card" x-bind:class="saveClass" class="flex h-12 items-center justify-center rounded-md px-4 text-[15px] font-medium leading-[18px] disabled:cursor-default disabled:opacity-40">Save image</button>

                    <div class="flex flex-col gap-2">
                        <p class="eyebrow">Or send the link</p>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" x-on:click="copyShareLink" x-text="copyLabel" class="flex h-11 items-center justify-center rounded-md border border-rule-strong px-2 text-small font-medium hover:border-ink"></button>
                            <template x-for="channel in channels" x-bind:key="channel.id">
                                <a x-bind:href="channel.url" x-bind:target="channel.target" rel="noopener noreferrer" x-on:click="trackChannel(channel.id)" x-text="channel.label" class="flex h-11 items-center justify-center rounded-md border border-rule-strong px-2 text-small font-medium hover:border-ink"></a>
                            </template>
                        </div>
                        <p role="status" class="min-h-5 text-small text-ink-muted" x-text="shareStatus"></p>
                    </div>

                    <div class="flex flex-col gap-1 rounded-md bg-surface px-3.5 py-3">
                        <p class="eyebrow">The message</p>
                        <p class="text-small text-ink" x-text="shareText"></p>
                        <p class="break-all text-[13px] leading-[19px] text-ink-muted" x-text="shareUrl"></p>
                        <p x-show="isResultsKind" class="text-label leading-[17px] text-ink-muted">Anyone with this link can see my answers.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</dialog>
