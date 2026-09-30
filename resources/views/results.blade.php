<x-layouts.public title="Your results" :noindex="$isPreview">
    <div
        x-data="results"
        data-stances-url="{{ $stancesUrl }}"
        data-quiz-url="{{ $quizUrl }}"
        data-district-url="{{ $districtUrl }}"
        data-storage-key="{{ $storageKey }}"
        class="mx-auto flex max-w-2xl flex-col gap-10 px-4 py-10"
    >
        @if ($isPreview)
            <x-preview-notice />
        @elseif ($isSample)
            <x-sample-notice />
        @endif

        <p x-show="loading" class="text-zinc-500">Working out your results…</p>
        <p x-show="failed" x-cloak>Sorry, the results couldn't be loaded. Please refresh the page to try again.</p>

        <template x-if="!loading && !failed && !enoughAnswers">
            <div class="flex flex-col gap-4">
                <h1 class="text-2xl font-semibold tracking-tight">Answer a few more questions</h1>
                <p class="text-zinc-600 dark:text-zinc-400">
                    We need at least <span x-text="minimumAnswers"></span> Agree or Disagree answers to compare you fairly. You've given <span x-text="comparable"></span>.
                </p>
                <div><a x-bind:href="changeAnswersUrl" class="inline-flex rounded-md bg-zinc-900 px-4 py-2 font-medium text-white dark:bg-zinc-100 dark:text-zinc-900">Back to the quiz</a></div>
            </div>
        </template>

        <template x-if="!loading && !failed && enoughAnswers">
            <div class="flex flex-col gap-12">
                <section aria-labelledby="parties" class="flex flex-col gap-6">
                    <div class="flex flex-col gap-2">
                        <h1 id="parties" class="text-2xl font-semibold tracking-tight">How often each party voted the way you would have</h1>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                            Based on your <span x-text="comparable"></span> Agree or Disagree answers. Voting records up to <span x-text="data.data_as_of"></span>.
                        </p>
                    </div>

                    <ol class="flex flex-col gap-5">
                        <template x-for="party in rankedParties" x-bind:key="party.code">
                            <li class="flex flex-col gap-2">
                                <span class="sr-only" x-text="party.label"></span>
                                <div class="flex items-baseline justify-between gap-4" aria-hidden="true">
                                    <span class="font-medium" x-text="party.short_name"></span>
                                    <span class="shrink-0 whitespace-nowrap text-sm tabular-nums text-zinc-600 dark:text-zinc-400" x-text="party.summary"></span>
                                </div>
                                <div class="relative h-2 rounded-full bg-zinc-200 dark:bg-zinc-800" aria-hidden="true">
                                    <span class="absolute top-1/2 size-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-zinc-900 ring-2 ring-white dark:bg-zinc-100 dark:ring-zinc-950" x-bind:style="party.dotStyle"></span>
                                </div>
                            </li>
                        </template>
                    </ol>

                    <div x-show="partiesWithoutRecord.length" class="text-sm text-zinc-600 dark:text-zinc-400">
                        <p>Not enough shared votes to compare:
                            <template x-for="(party, index) in partiesWithoutRecord" x-bind:key="party.code">
                                <span><span x-text="party.short_name"></span><span x-show="index < partiesWithoutRecord.length - 1">, </span></span>
                            </template>
                        </p>
                    </div>
                </section>

                <section x-show="hasMembers" aria-labelledby="representatives" class="flex flex-col gap-6">
                    <div class="flex flex-col gap-2">
                        <h2 id="representatives" class="text-lg font-semibold">Your members of Parliament</h2>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                            How often your MLA and your region's five MLCs voted the way you would have, on the questions they voted on. MPs mostly vote with their party.
                        </p>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="district" class="text-sm font-medium">Your district</label>
                        <select id="district" x-on:change="chooseDistrict" class="w-full max-w-sm rounded-md border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900">
                            <option value="" x-bind:selected="!district">Choose your district…</option>
                            <template x-for="option in districts" x-bind:key="option.slug">
                                <option x-bind:value="option.slug" x-bind:selected="option.slug === district" x-text="option.name"></option>
                            </template>
                        </select>
                        <p class="text-xs text-zinc-500">Not sure? <a href="{{ route('districts.index') }}" class="underline underline-offset-4">Find it by suburb</a>. Your district stays in your browser, like your answers.</p>
                    </div>

                    <template x-if="representatives">
                        <div class="flex flex-col gap-4">
                            <p x-show="isVacant" class="text-sm text-zinc-600 dark:text-zinc-400"><span x-text="districtName"></span> has no member of the Legislative Assembly at present.</p>
                            <ol class="flex flex-col gap-5">
                                <template x-for="member in representativeRows" x-bind:key="member.slug">
                                    <li class="flex flex-col gap-2">
                                        <span class="sr-only" x-text="member.label"></span>
                                        <div class="flex items-baseline justify-between gap-4" aria-hidden="true">
                                            <span class="flex flex-col">
                                                <span class="font-medium" x-text="member.name"></span>
                                                <span class="text-sm text-zinc-500"><span x-text="member.party"></span> · <span x-text="member.role"></span></span>
                                            </span>
                                            <span class="shrink-0 whitespace-nowrap text-sm tabular-nums text-zinc-600 dark:text-zinc-400" x-text="member.summary"></span>
                                        </div>
                                        <div x-show="member.hasScore" class="relative h-2 rounded-full bg-zinc-200 dark:bg-zinc-800" aria-hidden="true">
                                            <span class="absolute top-1/2 size-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-zinc-900 ring-2 ring-white dark:bg-zinc-100 dark:ring-zinc-950" x-bind:style="member.dotStyle"></span>
                                        </div>
                                    </li>
                                </template>
                            </ol>
                            <p class="text-sm"><a x-bind:href="districtPageUrl" class="underline underline-offset-4">See how they voted, question by question</a></p>
                        </div>
                    </template>
                </section>

                <section aria-labelledby="answers" class="flex flex-col gap-4">
                    <h2 id="answers" class="text-lg font-semibold">Question by question</h2>

                    <div class="flex flex-col divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                        <template x-for="policy in answeredPolicies" x-bind:key="policy.id">
                            <details class="group py-4">
                                <summary class="flex cursor-pointer list-none items-start justify-between gap-4">
                                    <span class="flex flex-col gap-1">
                                        <span class="text-xs uppercase tracking-wide text-zinc-500" x-text="policy.topic"></span>
                                        <span class="font-medium" x-text="policy.question"></span>
                                    </span>
                                    <span class="shrink-0 text-sm text-zinc-600 dark:text-zinc-400">You: <span x-text="policy.yourAnswer"></span></span>
                                </summary>

                                <table class="mt-4 w-full text-sm">
                                    <thead class="text-left text-zinc-500">
                                        <tr>
                                            <th scope="col" class="py-1 font-normal">Party</th>
                                            <th scope="col" class="py-1 font-normal">How they voted</th>
                                            <th scope="col" class="py-1 font-normal"><span class="sr-only">Compared with you</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="party in policy.parties" x-bind:key="party.code">
                                            <tr class="border-t border-zinc-100 dark:border-zinc-900">
                                                <th scope="row" class="py-1.5 pr-4 text-left font-normal" x-text="party.name"></th>
                                                <td class="py-1.5 pr-4" x-text="party.stance"></td>
                                                <td class="py-1.5 text-right" x-bind:class="party.verdictClass" x-text="party.verdict"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                                <p x-show="policy.url" class="mt-3 text-sm"><a x-bind:href="policy.url" class="underline underline-offset-4">See the votes behind this question</a></p>
                            </details>
                        </template>
                    </div>
                </section>

                <section aria-labelledby="next" class="flex flex-col gap-3">
                    <h2 id="next" class="sr-only">What next</h2>
                    <div class="flex flex-wrap gap-3">
                        <a x-bind:href="changeAnswersUrl" class="inline-flex rounded-md border border-zinc-300 px-4 py-2 hover:border-zinc-500 dark:border-zinc-700">Change my answers</a>
                        <button type="button" x-on:click="copyLink" class="inline-flex rounded-md border border-zinc-300 px-4 py-2 hover:border-zinc-500 dark:border-zinc-700">
                            <span x-show="!copied">Copy a link to these results</span>
                            <span x-show="copied">Link copied</span>
                        </button>
                    </div>
                    <p class="text-xs text-zinc-500">The link stores your answers after the <code>#</code>, which browsers never send to a server.</p>
                </section>
            </div>
        </template>
    </div>
</x-layouts.public>
