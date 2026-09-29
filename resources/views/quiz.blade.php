<x-layouts.public title="Quiz">
    <div
        x-data="quiz"
        data-stances-url="{{ asset('stances/sample.json') }}"
        data-results-url="{{ route('results') }}"
        class="mx-auto flex max-w-2xl flex-col gap-8 px-4 py-10"
    >
        <x-sample-notice />

        <p x-show="loading" class="text-zinc-500">Loading questions…</p>
        <p x-show="failed" x-cloak class="text-zinc-700 dark:text-zinc-300">Sorry, the questions couldn't be loaded. Please refresh the page to try again.</p>

        <template x-if="current">
            <section
                tabindex="-1"
                aria-labelledby="question"
                x-on:keydown="onKey"
                class="flex flex-col gap-8 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-zinc-400"
            >
                <div class="flex flex-col gap-2">
                    <div class="flex items-baseline justify-between gap-4 text-sm text-zinc-500 dark:text-zinc-400">
                        <span x-text="current.topic"></span>
                        <span x-text="positionLabel"></span>
                    </div>
                    <div class="h-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800" aria-hidden="true">
                        <div class="h-1 rounded-full bg-zinc-900 transition-all dark:bg-zinc-100" x-bind:style="progress"></div>
                    </div>
                </div>

                <h1 id="question" class="text-2xl font-semibold leading-snug tracking-tight sm:text-3xl" x-text="current.question"></h1>

                <div class="flex flex-col gap-3">
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" x-on:click="agree" x-bind:aria-pressed="isSelected('a')" x-bind:class="answerClass('a')" class="rounded-md border px-4 py-4 text-lg font-medium">Agree</button>
                        <button type="button" x-on:click="disagree" x-bind:aria-pressed="isSelected('d')" x-bind:class="answerClass('d')" class="rounded-md border px-4 py-4 text-lg font-medium">Disagree</button>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" x-on:click="unsure" x-bind:aria-pressed="isSelected('u')" x-bind:class="answerClass('u')" class="rounded-md border px-4 py-2 text-zinc-700 dark:text-zinc-300">Unsure</button>
                        <button type="button" x-on:click="skip" class="rounded-md border border-transparent px-4 py-2 text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100">Skip</button>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-4 text-sm">
                    <button type="button" x-on:click="back" x-bind:disabled="isFirst" class="text-zinc-600 underline-offset-4 hover:underline disabled:invisible dark:text-zinc-400">&larr; Previous question</button>
                    <button type="button" x-show="canSeeResults" x-on:click="finish" class="font-medium underline-offset-4 hover:underline">See my results now &rarr;</button>
                    <span x-show="!canSeeResults" class="text-zinc-500">Answer <span x-text="remainingForResults"></span> more to see results</span>
                </div>

                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    Keyboard: once you've clicked an answer, press <kbd>A</kbd> agree, <kbd>D</kbd> disagree, <kbd>U</kbd> unsure, <kbd>S</kbd> skip, <kbd>&larr;</kbd> previous.
                </p>
            </section>
        </template>

        <p class="sr-only" aria-live="polite" x-text="announcement"></p>

        <div x-show="!loading && !failed" x-cloak>
            <button type="button" x-on:click="startAgain" class="text-sm text-zinc-500 underline-offset-4 hover:underline">Clear my answers and start again</button>
        </div>
    </div>
</x-layouts.public>
