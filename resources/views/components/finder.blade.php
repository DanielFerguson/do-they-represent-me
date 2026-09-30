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
    {{ $attributes->class('relative flex flex-col gap-2') }}
>
    <label for="finder-input" class="font-medium">Suburb, town or postcode</label>
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
        class="w-full rounded-md border border-zinc-500 bg-white px-4 py-3 text-lg dark:border-zinc-500 dark:bg-zinc-900"
    >
    <p id="finder-hint" class="text-sm text-zinc-600 dark:text-zinc-400">
        For example, Ballarat or 3350. Or <a href="{{ route('districts.index') }}" class="underline underline-offset-4">browse all 88 districts</a>.
    </p>

    <ul
        id="finder-listbox"
        role="listbox"
        aria-label="Suburbs"
        x-show="isOpen"
        x-cloak
        class="absolute left-0 right-0 top-[5.5rem] z-10 max-h-80 overflow-y-auto rounded-md border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
    >
        <template x-for="(locality, index) in matches" x-bind:key="locality.name + locality.postcodes.join()">
            <li
                role="option"
                x-bind:id="optionId(index)"
                x-bind:aria-selected="index === active"
                x-bind:class="optionClass(index)"
                x-on:mousedown.prevent
                x-on:click="select(index)"
                class="flex cursor-pointer items-baseline justify-between gap-4 px-4 py-2"
            >
                <span class="font-medium" x-text="locality.name"></span>
                <span class="text-sm text-zinc-600 dark:text-zinc-400" x-text="optionDetail(locality)"></span>
            </li>
        </template>
    </ul>

    <p class="sr-only" aria-live="polite" x-text="status"></p>
    <p x-show="noMatches && !isOpen" x-cloak class="text-sm text-zinc-600 dark:text-zinc-400">No suburb or postcode matches. Try another spelling, or browse the districts.</p>
    <p x-show="failed" x-cloak role="alert" class="text-sm text-zinc-600 dark:text-zinc-400">Sorry, the suburb list couldn't be loaded. You can <a href="{{ route('districts.index') }}" class="underline underline-offset-4">browse the districts</a> instead.</p>

    <div x-show="chosen" x-cloak x-ref="choices" tabindex="-1" role="group" aria-labelledby="finder-split" class="flex flex-col gap-2 rounded-md border border-zinc-200 p-4 dark:border-zinc-800">
        <p id="finder-split"><span x-text="chosenName"></span> is split between districts. Choose yours:</p>
        <ul class="flex flex-col gap-1">
            <template x-for="district in chosenDistricts" x-bind:key="district.slug">
                <li>
                    <a x-bind:href="districtLink(district.slug)" x-on:click="remember(district.slug)" class="underline underline-offset-4" x-text="district.label"></a>
                </li>
            </template>
        </ul>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            Not sure? <a href="{{ config('site.vec_lookup_url') }}" class="underline underline-offset-4" rel="noopener">Check your address with the Victorian Electoral Commission</a>.
        </p>
    </div>
</div>
