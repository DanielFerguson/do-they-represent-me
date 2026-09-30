@props(['name', 'url', 'image', 'alt'])

{{--
    The Share button on a district page, and the dialog it opens: the page's picture as it will look in a link preview, and ways to send
    the link. Nothing from the visitor goes in it. Its state and methods are in `shareDistrict` in resources/js/app.js.
--}}
<div x-data="shareDistrict" data-url="{{ $url }}" data-name="{{ $name }}" class="contents">
    <button type="button" x-on:click="open" class="flex h-12 items-center justify-center gap-2 rounded-md border border-rule-strong px-5 text-[15px] font-medium hover:border-ink">
        <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" class="shrink-0"><path d="M8 10.5V2M8 2L4.75 5.25M8 2l3.25 3.25M3 9v4.25c0 .414.336.75.75.75h8.5a.75.75 0 0 0 .75-.75V9" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
        Share<span class="sr-only"> this district</span>
    </button>

    <dialog
        x-ref="sheet"
        x-on:click="onSheetClick"
        aria-labelledby="share-district-title"
        class="m-0 mt-auto max-h-[92dvh] w-full max-w-none overflow-y-auto rounded-t-2xl bg-ground p-0 text-ink backdrop:bg-ink/50 lg:m-auto lg:w-[440px] lg:max-w-[calc(100vw-2rem)] lg:rounded-lg"
    >
        <div class="flex flex-col gap-4 px-5 pb-7 pt-3 lg:px-6 lg:pb-6 lg:pt-5">
            <div class="flex justify-center lg:hidden" aria-hidden="true"><span class="h-1 w-9 rounded-full bg-rule-strong"></span></div>

            <div class="flex items-center justify-between gap-3">
                <h2 id="share-district-title" class="text-h2 font-semibold tracking-tight">Share this district</h2>
                <button type="button" x-on:click="close" aria-label="Close" class="flex size-9 shrink-0 items-center justify-center rounded-md border border-rule-strong hover:border-ink">
                    <svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true"><path d="M2 2l8 8M10 2l-8 8" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>
                </button>
            </div>

            <img src="{{ $image }}" alt="{{ $alt }}" width="1200" height="630" loading="lazy" class="block aspect-[1200/630] w-full rounded-sm border border-rule bg-white object-cover">

            <button type="button" x-show="canNative" x-cloak x-on:click="nativeShare" class="flex h-12 items-center justify-center gap-2 rounded-md bg-ink px-4 text-[15px] font-medium leading-[18px] text-ground hover:bg-ink/85">
                <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" class="shrink-0"><path d="M8 10.5V2M8 2L4.75 5.25M8 2l3.25 3.25M3 9v4.25c0 .414.336.75.75.75h8.5a.75.75 0 0 0 .75-.75V9" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                Share…
            </button>

            <div class="grid grid-cols-3 gap-2">
                <button type="button" x-on:click="copyLink" x-text="copyLabel" class="flex h-11 items-center justify-center rounded-md border border-rule-strong px-2 text-small font-medium hover:border-ink"></button>
                <template x-for="channel in channels" x-bind:key="channel.id">
                    <a x-bind:href="channel.url" x-bind:target="channel.target" rel="noopener noreferrer" x-on:click="trackChannel(channel.id)" x-text="channel.label" class="flex h-11 items-center justify-center rounded-md border border-rule-strong px-2 text-small font-medium hover:border-ink"></a>
                </template>
            </div>
            <p role="status" class="min-h-5 text-small text-ink-muted" x-text="status"></p>
            <p class="text-label leading-[17px] text-ink-muted">Links and previews show the district and its members only. They don't include your answers.</p>
        </div>
    </dialog>
</div>
