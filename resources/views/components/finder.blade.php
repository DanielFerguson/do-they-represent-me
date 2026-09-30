@props(['url'])

{{--
    Suburb and postcode search. The list of localities is downloaded once and
    searched in the browser; nothing typed here is sent to the server.
--}}
<div
    x-data="finder"
    data-localities-url="{{ $url }}"
    data-district-url="{{ route('districts.show', '__district__', absolute: false) }}"
    x-on:click.outside="close"
    {{ $attributes->class('flex flex-col gap-2') }}
>
    <label for="finder-input" class="text-small font-medium leading-[18px]">Your suburb or town</label>
    <div class="relative">
        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 lg:left-4 lg:size-[18px]"><circle cx="8" cy="8" r="5.5" fill="none" stroke="currentColor" stroke-width="1.5" /><path d="M12.5 12.5L16 16" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>
        <input
            id="finder-input"
            type="text"
            role="combobox"
            autocomplete="off"
            spellcheck="false"
            aria-autocomplete="list"
            aria-controls="finder-listbox"
            aria-describedby="finder-hint"
            x-bind:aria-expanded="isOpen"
            x-bind:aria-activedescendant="activeId"
            x-model="query"
            x-on:focus="load"
            x-on:input="onInput"
            x-on:keydown="onKeydown"
            x-on:blur="close"
            class="h-13 w-full rounded-md border-2 border-ink bg-ground pl-10 pr-3.5 text-base text-ink lg:h-14 lg:pl-11 lg:pr-4 lg:text-body"
        >

        <ul
            id="finder-listbox"
            role="listbox"
            aria-label="Suburbs"
            x-show="isOpen"
            x-cloak
            class="absolute inset-x-0 top-full z-10 mt-1 max-h-80 overflow-y-auto rounded-md border border-rule-strong bg-ground py-1 shadow-lg"
        >
            <template x-for="(locality, index) in matches" x-bind:key="locality.name + locality.postcodes.join()">
                <li
                    role="option"
                    x-bind:id="optionId(index)"
                    x-bind:aria-selected="index === active"
                    x-bind:class="optionClass(index)"
                    x-on:mousedown.prevent
                    x-on:click="select(index)"
                    class="flex cursor-pointer items-baseline justify-between gap-4 px-4 py-2.5 hover:bg-surface"
                >
                    <span class="text-[15px] font-medium leading-[18px]" x-text="locality.name"></span>
                    <span class="text-right text-[13px] leading-4 text-ink-muted" x-text="optionDetail(locality)"></span>
                </li>
            </template>
        </ul>
    </div>
    <p id="finder-hint" class="text-[13px] leading-[19px] text-ink-muted lg:text-small lg:leading-[21px]">
        For example, Ballarat or 3350. Or <a href="{{ route('districts.index') }}#regions" class="link">browse all 88 districts</a>.
    </p>

    <p class="sr-only" aria-live="polite" x-text="status"></p>
    <p x-show="noMatches && !isOpen" x-cloak class="text-small leading-[21px] text-ink-muted">No suburb or postcode matches. Try another spelling, or browse the districts.</p>
    <p x-show="failed" x-cloak role="alert" class="text-small leading-[21px] text-ink-muted">Sorry, the suburb list couldn't be loaded. You can <a href="{{ route('districts.index') }}#regions" class="link text-ink">browse the districts</a> instead.</p>

    <div x-show="chosen" x-cloak x-ref="choices" tabindex="-1" role="group" aria-labelledby="finder-split" class="flex flex-col rounded-md border border-rule-strong px-4 pb-3 pt-1 lg:px-5 lg:pb-4 lg:pt-2">
        <div class="flex flex-col gap-1 py-2.5 lg:py-3">
            <p id="finder-split" class="text-small font-semibold leading-[18px] lg:text-[15px]" x-text="chosenHeading"></p>
            <p class="text-[13px] leading-[19px] text-ink-muted lg:text-small lg:leading-[21px]">
                <span x-text="chosenSummary"></span>
                <span class="hidden lg:inline">If you're not sure which side of the line you're on, check your exact address.</span>
            </p>
        </div>
        <ul class="flex flex-col">
            <template x-for="district in chosenDistricts" x-bind:key="district.slug">
                <li class="border-t border-rule">
                    <a x-bind:href="districtLink(district.slug)" x-on:click="remember(district.slug)" class="group flex h-12 items-center gap-2.5 lg:h-13 lg:gap-4">
                        <span class="min-w-0 flex-1 text-[15px] font-medium leading-[18px] group-hover:underline group-hover:decoration-rule-strong group-hover:underline-offset-4 lg:text-base lg:leading-5" x-text="district.name"></span>
                        <span class="text-right text-[13px] leading-4 text-ink-muted lg:text-small lg:leading-[18px]"><span x-text="district.shareText"></span><span x-show="district.isLargest" class="hidden lg:inline"> of residents</span></span>
                        <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" class="size-3 shrink-0 lg:size-3.5"><path d="M5 2.5L9.5 7L5 11.5" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>
                    </a>
                </li>
            </template>
        </ul>
        <p class="border-t border-rule pt-3 text-[13px] leading-4 lg:pt-3.5 lg:text-small lg:leading-[18px]">
            <a href="{{ config('site.vec_lookup_url') }}" class="underline underline-offset-4" rel="noopener">Check your address with the <span class="hidden lg:inline">Victorian Electoral Commission</span><abbr title="Victorian Electoral Commission" class="no-underline lg:hidden">VEC</abbr> <span aria-hidden="true">↗</span></a>
        </p>
    </div>
</div>
