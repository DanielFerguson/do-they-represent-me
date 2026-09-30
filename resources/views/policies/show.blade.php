<x-layouts.public :title="$policy->title" :noindex="$isPreview" :description="$policy->question">
    <article class="mx-auto flex max-w-3xl flex-col gap-10 px-4 py-10">
        @if ($isPreview)
            <x-preview-notice />
        @endif

        <header class="flex flex-col gap-3">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                @unless ($isPreview)
                    <a href="{{ route('policies.index') }}" class="underline-offset-4 hover:underline">Questions</a>
                    <span aria-hidden="true">/</span>
                @endunless
                {{ $policy->topic }}
            </p>
            <h1 class="text-2xl font-semibold leading-snug tracking-tight sm:text-3xl">{{ $policy->question }}</h1>
            <p class="text-zinc-600 dark:text-zinc-400">{{ $policy->title }}</p>
        </header>

        @if ($policy->description)
            <section aria-labelledby="background" class="flex flex-col gap-3">
                <h2 id="background" class="text-lg font-semibold">Background</h2>
                <x-paragraphs :text="$policy->description" class="leading-relaxed text-zinc-700 dark:text-zinc-300" />
            </section>
        @endif

        @if ($policy->arguments_for || $policy->arguments_against)
            <section aria-labelledby="arguments" class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <h2 id="arguments" class="text-lg font-semibold">What each side said</h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Summarised from the debate in Parliament, with sources below.</p>
                </div>
                <div class="grid gap-6 sm:grid-cols-2">
                    @if ($policy->arguments_for)
                        <div class="flex flex-col gap-2">
                            <h3 class="font-medium">For "agree"</h3>
                            <x-paragraphs :text="$policy->arguments_for" class="text-sm leading-relaxed text-zinc-700 dark:text-zinc-300" />
                        </div>
                    @endif
                    @if ($policy->arguments_against)
                        <div class="flex flex-col gap-2">
                            <h3 class="font-medium">For "disagree"</h3>
                            <x-paragraphs :text="$policy->arguments_against" class="text-sm leading-relaxed text-zinc-700 dark:text-zinc-300" />
                        </div>
                    @endif
                </div>
            </section>
        @endif

        <section aria-labelledby="parties" class="flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <h2 id="parties" class="text-lg font-semibold">How the parties voted</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    Across the linked votes below. Second and third readings count five times as much as other votes. Parties are in alphabetical order. <a href="{{ route('methodology') }}#scores" class="underline underline-offset-4">How this is worked out</a>
                </p>
            </div>

            @if ($parties === [])
                <p class="text-zinc-600 dark:text-zinc-400">No party has a record on these votes yet.</p>
            @else
                <table class="w-full text-sm">
                    <thead class="text-left text-zinc-500">
                        <tr>
                            <th scope="col" class="py-1 pr-4 font-normal">Party</th>
                            <th scope="col" class="py-1 pr-4 font-normal">Record</th>
                            <th scope="col" class="py-1 text-right font-normal">Votes with a party position</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($parties as ['party' => $party, 'stance' => $stance])
                            <tr class="border-t border-zinc-200 dark:border-zinc-800">
                                <th scope="row" class="py-2 pr-4 text-left font-normal">{{ $party->display_name ?? $party->name }}</th>
                                <td class="py-2 pr-4">{{ $stance->text() }}</td>
                                <td class="py-2 text-right tabular-nums text-zinc-600 dark:text-zinc-400">{{ $stance->votes()['voted'] }} of {{ $stance->votes()['possible'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section aria-labelledby="votes" class="flex flex-col gap-6">
            <div class="flex flex-col gap-1">
                <h2 id="votes" class="text-lg font-semibold">The votes behind this question</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Each vote (division) recorded in Parliament that this question is based on, in date order.</p>
            </div>

            @foreach ($divisions as ['link' => $link, 'parties' => $splits, 'independents' => $independents, 'ayes' => $ayes, 'noes' => $noes])
                @php($division = $link->division)
                <article class="flex flex-col gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-800" aria-labelledby="division-{{ $division->id }}">
                    <div class="flex flex-col gap-1">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $division->sitting_date->format('j F Y') }} · {{ $division->house->name }}
                        </p>
                        <h3 id="division-{{ $division->id }}" class="font-medium">{{ $division->item_title ? Str::title(Str::lower($division->item_title)) : 'Division' }}</h3>
                    </div>

                    <dl class="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-[10rem_1fr]">
                        <dt class="text-zinc-500">Question put</dt>
                        <dd>{{ $division->question }}</dd>
                        <dt class="text-zinc-500">Result</dt>
                        <dd>{{ $division->result }} Ayes {{ $division->ayes_count }}, Noes {{ $division->noes_count }}.</dd>
                        <dt class="text-zinc-500">Matches "agree"</dt>
                        <dd>{{ $link->direction === App\Enums\VoteValue::Aye ? 'An Aye vote' : 'A No vote' }}</dd>
                        <dt class="text-zinc-500">Weight</dt>
                        <dd>{{ $link->is_strong ? 'Strong: a second or third reading, counted five times' : 'Normal' }}</dd>
                        @if ($link->rationale)
                            <dt class="text-zinc-500">Why it's linked</dt>
                            <dd>{{ $link->rationale }}</dd>
                        @endif
                    </dl>

                    @if ($division->is_free_vote)
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">This was a free (conscience) vote, so no party is given a position on it.</p>
                    @endif

                    @if ($splits !== [])
                        <table class="w-full text-sm">
                            <caption class="sr-only">How each party voted</caption>
                            <thead class="text-left text-zinc-500">
                                <tr>
                                    <th scope="col" class="py-1 pr-4 font-normal">Party</th>
                                    <th scope="col" class="py-1 pr-2 text-right font-normal">Aye</th>
                                    <th scope="col" class="py-1 pr-2 text-right font-normal">No</th>
                                    <th scope="col" class="py-1 pr-4 text-right font-normal">Didn't vote</th>
                                    <th scope="col" class="py-1 font-normal">Party position</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($splits as $split)
                                    <tr class="border-t border-zinc-100 dark:border-zinc-900">
                                        <th scope="row" class="py-1.5 pr-4 text-left font-normal">{{ $split['party']->display_name ?? $split['party']->name }}</th>
                                        <td class="py-1.5 pr-2 text-right tabular-nums">{{ $split['ayes'] }}</td>
                                        <td class="py-1.5 pr-2 text-right tabular-nums">{{ $split['noes'] }}</td>
                                        <td class="py-1.5 pr-4 text-right tabular-nums text-zinc-500">{{ $split['absent'] }}</td>
                                        <td class="py-1.5">{{ $split['position']->position->label() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    @if ($independents !== [])
                        <p class="text-sm">
                            <span class="text-zinc-500">Independents, each counted on their own:</span>
                            @foreach ($independents as $vote)
                                {{ $vote->member->display_name }} ({{ $vote->vote === App\Enums\VoteValue::Aye ? 'Aye' : 'No' }}){{ $loop->last ? '' : ',' }}
                            @endforeach
                        </p>
                    @endif

                    <details class="text-sm">
                        <summary class="cursor-pointer text-zinc-600 underline-offset-4 hover:underline dark:text-zinc-400">Every name</summary>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <div>
                                <h4 class="font-medium">Ayes ({{ count($ayes) }})</h4>
                                <p class="mt-1 text-zinc-700 dark:text-zinc-300">{{ implode(', ', $ayes) }}</p>
                            </div>
                            <div>
                                <h4 class="font-medium">Noes ({{ count($noes) }})</h4>
                                <p class="mt-1 text-zinc-700 dark:text-zinc-300">{{ implode(', ', $noes) }}</p>
                            </div>
                        </div>
                    </details>

                    @if ($division->proceedingsDocument->sourceUrl())
                        <p class="text-sm">
                            <a href="{{ $division->proceedingsDocument->sourceUrl() }}" class="underline underline-offset-4" rel="noopener">{{ $division->proceedingsDocument->title }}</a>
                            <span class="text-zinc-500">(official record, {{ $division->reference() }})</span>
                        </p>
                    @endif
                </article>
            @endforeach
        </section>

        @if ($sources !== [])
            <section aria-labelledby="sources" class="flex flex-col gap-3">
                <h2 id="sources" class="text-lg font-semibold">Sources</h2>
                <ul class="flex list-disc flex-col gap-1 pl-5 text-sm text-zinc-700 dark:text-zinc-300">
                    @foreach ($sources as $source)
                        <li>
                            @if ($source['url'])
                                <a href="{{ $source['url'] }}" class="underline underline-offset-4" rel="noopener">{{ $source['label'] }}</a>
                            @else
                                {{ $source['label'] }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @unless ($isPreview)
            <div>
                <a href="{{ route('quiz') }}" class="inline-flex items-center rounded-md bg-zinc-900 px-5 py-3 font-medium text-white hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-300">Take the quiz</a>
            </div>
        @endunless
    </article>
</x-layouts.public>
