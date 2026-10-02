@php
    $houses = collect($divisions)->map(fn (array $row) => $row['link']->division->house)->unique('id');
    $coverage = implode(' · ', array_filter([
        match ($houses->count()) {
            0 => null,
            1 => Str::after($houses->first()->name, 'Legislative ').' only',
            default => 'Both houses',
        },
        count($divisions) > 0 ? count($divisions).' '.Str::plural('vote', count($divisions)) : null,
    ]));
    $reportUrl = route('contact', ['topic' => 'correction', 'policy' => $policy->slug]);
    $sections = array_filter([
        'background' => $policy->description ? 'Background' : null,
        'arguments' => $policy->arguments_for || $policy->arguments_against ? 'What each side said' : null,
        'parties' => 'How the parties voted',
        'votes' => count($divisions) === 1 ? 'The vote behind this question' : 'The votes behind this question',
        'sources' => $sources !== [] ? 'Sources' : null,
    ]);
    $sectionClass = 'flex scroll-mt-6 flex-col gap-3 border-t border-ink pt-3.5 lg:gap-4 lg:pt-5';
    $headingClass = 'text-[18px] font-semibold leading-[26px] lg:text-h2 lg:leading-7';
    $introClass = 'text-[13px] leading-[19px] text-ink-muted lg:text-small lg:leading-[21px]';
    $columnHeadClass = 'h-7 text-[11px] font-medium uppercase leading-[14px] tracking-label text-ink-muted lg:h-8 lg:text-label';
    $cellClass = 'h-[34px] border-t border-rule text-[13px] leading-4 lg:h-9 lg:text-small lg:leading-[18px]';
@endphp

<x-layouts.public :page-type="$isPreview ? null : 'policy'" :breadcrumbs="[['name' => 'Questions', 'url' => route('policies.index')]]" share-image="images/share-questions.png" :title="$policy->title" :noindex="$isPreview" :description="$policy->question">
    <div class="mx-auto flex max-w-page flex-col gap-6 px-5 pb-12 pt-5 lg:flex-row lg:items-start lg:justify-between lg:gap-12 lg:pb-24 lg:pt-18">
        <article class="flex min-w-0 flex-col gap-6 lg:max-w-[704px] lg:flex-1 lg:gap-12">
            @if ($isPreview)
                <x-preview-notice />
            @endif

            <header class="flex flex-col gap-2.5 lg:gap-4">
                <nav aria-label="Breadcrumb" class="flex items-baseline gap-1.5 text-[13px] leading-4 text-ink-muted lg:gap-2 lg:text-small lg:leading-[18px]">
                    @unless ($isPreview)
                        <a href="{{ route('policies.index') }}" class="link">Questions</a>
                        <span aria-hidden="true" class="text-rule-strong">/</span>
                    @endunless
                    {{ $policy->topic }}
                </nav>
                <h1 class="text-[26px] font-semibold leading-8 tracking-display lg:text-h1">{{ $policy->question }}</h1>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <p class="hidden text-[15px] leading-[18px] text-ink-muted lg:block">{{ $policy->title }}</p>
                    @if ($coverage !== '')
                        <p class="self-start rounded-sm border border-rule-strong px-2 py-0.5 text-[11px] font-semibold uppercase leading-[14px] tracking-label lg:self-auto lg:text-label lg:font-medium">{{ $coverage }}</p>
                    @endif
                </div>
            </header>

            @if ($policy->description)
                <section aria-labelledby="background" class="{{ $sectionClass }}">
                    <h2 id="background" class="{{ $headingClass }}">Background</h2>
                    <x-paragraphs :text="$policy->description" class="text-[15px] leading-6 lg:text-body" />
                </section>
            @endif

            @if ($policy->arguments_for || $policy->arguments_against)
                <section aria-labelledby="arguments" class="{{ $sectionClass }}">
                    <div class="flex flex-col gap-1">
                        <h2 id="arguments" class="{{ $headingClass }}">What each side said</h2>
                        <p class="{{ $introClass }}">Summarised from the debate in Parliament, with sources below.</p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row lg:gap-6">
                        @if ($policy->arguments_for)
                            <div class="flex flex-1 flex-col gap-1.5 border-l-2 border-rule-strong pl-3.5 lg:gap-2.5 lg:pl-5">
                                <h3 class="text-[11px] font-semibold uppercase leading-[14px] tracking-label lg:text-label">For “agree”</h3>
                                <x-paragraphs :text="$policy->arguments_for" class="text-small leading-[22px] lg:text-[15px] lg:leading-6" />
                            </div>
                        @endif
                        @if ($policy->arguments_against)
                            <div class="flex flex-1 flex-col gap-1.5 border-l-2 border-rule-strong pl-3.5 lg:gap-2.5 lg:pl-5">
                                <h3 class="text-[11px] font-semibold uppercase leading-[14px] tracking-label lg:text-label">For “disagree”</h3>
                                <x-paragraphs :text="$policy->arguments_against" class="text-small leading-[22px] lg:text-[15px] lg:leading-6" />
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            <section aria-labelledby="parties" class="{{ $sectionClass }}">
                <div class="flex flex-col gap-1">
                    <h2 id="parties" class="{{ $headingClass }}">How the parties voted</h2>
                    <p class="{{ $introClass }}">
                        Across the linked votes below. Second and third readings count five times as much as other votes. Parties are in alphabetical order. <a href="{{ route('methodology') }}#scores" class="link">How this is worked out</a>
                    </p>
                </div>

                @if ($parties === [])
                    <p class="rounded-md bg-surface p-4 text-small leading-[21px] text-ink-muted lg:p-5">No party has a record on these votes yet.</p>
                @else
                    <table class="w-full">
                        <caption class="sr-only">How each party voted across the linked votes</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="{{ $columnHeadClass }} pr-3 text-left">Party</th>
                                <th scope="col" class="{{ $columnHeadClass }} pr-3 text-left">Record</th>
                                <th scope="col" class="{{ $columnHeadClass }} text-right">Votes with a party position</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($parties as ['party' => $party, 'stance' => $stance])
                                <tr>
                                    <th scope="row" class="{{ $cellClass }} py-2 pr-3 text-left font-normal">
                                        <span class="flex items-center gap-2 lg:gap-2.5"><x-party-swatch :code="$party->short_name" />{{ $party->display_name ?? $party->name }}</span>
                                    </th>
                                    <td class="{{ $cellClass }} py-2 pr-3">{{ $stance->text() }}</td>
                                    <td class="{{ $cellClass }} py-2 text-right tabular-nums text-ink-muted">{{ $stance->votes()['voted'] }} of {{ $stance->votes()['possible'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            <section aria-labelledby="votes" class="{{ $sectionClass }}">
                <div class="flex flex-col gap-1">
                    <h2 id="votes" class="{{ $headingClass }}">{{ $sections['votes'] }}</h2>
                    <p class="{{ $introClass }}">Each vote (division) recorded in Parliament that this question is based on, in date order.</p>
                </div>

                @foreach ($divisions as ['link' => $link, 'parties' => $splits, 'independents' => $independents, 'ayes' => $ayes, 'noes' => $noes])
                    @php
                        $division = $link->division;
                        $divisionTitle = $division->item_title ? Str::title(Str::lower($division->item_title)) : null;
                    @endphp
                    <article class="flex flex-col gap-3 rounded-md border border-rule-strong p-4 lg:gap-4 lg:p-6" aria-labelledby="division-{{ $division->id }}">
                        <div class="flex flex-col gap-0.5 lg:gap-1">
                            <p class="text-label text-ink-muted lg:text-[13px] lg:leading-4">
                                {{ $division->sitting_date->format('j F Y') }} · {{ $division->house->name }}
                            </p>
                            <h3 id="division-{{ $division->id }}" class="text-base font-semibold leading-5 lg:text-body lg:leading-[22px]">{{ $divisionTitle ?? 'Division' }}</h3>
                        </div>

                        <dl class="flex flex-col text-[13px] leading-[18px] lg:text-small">
                            <div class="flex flex-col gap-0.5 border-t border-rule py-2 sm:flex-row sm:gap-4">
                                <dt class="shrink-0 text-ink-muted sm:w-[140px]">Question put</dt>
                                <dd>{{ $division->question }}</dd>
                            </div>
                            <div class="flex flex-col gap-0.5 border-t border-rule py-2 sm:flex-row sm:gap-4">
                                <dt class="shrink-0 text-ink-muted sm:w-[140px]">Result</dt>
                                <dd>{{ $division->result }} Ayes {{ $division->ayes_count }}, Noes {{ $division->noes_count }}.</dd>
                            </div>
                            <div class="flex flex-col gap-0.5 border-t border-rule py-2 sm:flex-row sm:gap-4">
                                <dt class="shrink-0 text-ink-muted sm:w-[140px]">Matches “agree”</dt>
                                <dd>{{ $link->direction === App\Enums\VoteValue::Aye ? 'An Aye vote' : 'A No vote' }}</dd>
                            </div>
                            <div class="flex flex-col gap-0.5 border-t border-rule py-2 sm:flex-row sm:gap-4">
                                <dt class="shrink-0 text-ink-muted sm:w-[140px]">Weight</dt>
                                <dd>{{ $link->is_strong ? 'Strong: a second or third reading, counted five times' : 'Normal' }}</dd>
                            </div>
                            @if ($link->rationale)
                                <div class="flex flex-col gap-0.5 border-t border-rule py-2 sm:flex-row sm:gap-4">
                                    <dt class="shrink-0 text-ink-muted sm:w-[140px]">Why it's linked</dt>
                                    <dd>{{ $link->rationale }}</dd>
                                </div>
                            @endif
                        </dl>

                        @if ($division->is_free_vote)
                            <p class="rounded-md bg-surface px-4 py-3 text-[13px] leading-[19px] text-ink-muted lg:text-small lg:leading-[21px]">This was a free (conscience) vote, so no party is given a position on it.</p>
                        @endif

                        @if ($splits !== [])
                            <table class="w-full">
                                <caption class="sr-only">How each party voted on {{ $divisionTitle ?? 'this division' }}, {{ $division->sitting_date->format('j F Y') }}</caption>
                                <thead>
                                    <tr>
                                        <th scope="col" class="{{ $columnHeadClass }} pr-2 text-left lg:pr-3">Party</th>
                                        <th scope="col" class="{{ $columnHeadClass }} w-8 pr-2 text-right lg:w-15 lg:pr-3">Aye</th>
                                        <th scope="col" class="{{ $columnHeadClass }} w-8 pr-2 text-right lg:w-15 lg:pr-3">No</th>
                                        <th scope="col" class="{{ $columnHeadClass }} hidden w-27 pr-3 text-right sm:table-cell">Didn't vote</th>
                                        <th scope="col" class="{{ $columnHeadClass }} w-[84px] text-right lg:w-[120px]">Position</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($splits as $split)
                                        @php($hasNoPosition = $split['position']->position === App\Enums\PartyPosition::None)
                                        <tr>
                                            <th scope="row" class="{{ $cellClass }} py-2 pr-2 text-left font-normal lg:pr-3">
                                                <span class="flex items-center gap-2 lg:gap-2.5"><x-party-swatch :code="$split['party']->short_name" />{{ $split['party']->display_name ?? $split['party']->name }}</span>
                                            </th>
                                            <td class="{{ $cellClass }} pr-2 text-right tabular-nums lg:pr-3">{{ $split['ayes'] }}</td>
                                            <td class="{{ $cellClass }} pr-2 text-right tabular-nums lg:pr-3">{{ $split['noes'] }}</td>
                                            <td class="{{ $cellClass }} hidden pr-3 text-right tabular-nums text-ink-muted sm:table-cell">{{ $split['absent'] }}</td>
                                            <td @class([$cellClass, 'text-right', 'text-ink-muted' => $hasNoPosition])>{{ $hasNoPosition ? 'Did not vote' : $split['position']->position->label() }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif

                        @if ($independents !== [])
                            <p class="text-[13px] leading-[19px] lg:text-small lg:leading-[21px]">
                                <span class="text-ink-muted">Independents, each counted on their own:</span>
                                @foreach ($independents as $vote)
                                    {{ $vote->member->display_name }} ({{ $vote->vote === App\Enums\VoteValue::Aye ? 'Aye' : 'No' }}){{ $loop->last ? '' : ',' }}
                                @endforeach
                            </p>
                        @endif

                        <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-3 border-t border-rule pt-3.5 text-[13px] leading-4 lg:text-small lg:leading-[18px]">
                            <details class="group open:w-full">
                                <summary class="cursor-pointer list-none underline underline-offset-4 [&::-webkit-details-marker]:hidden">Every name (Ayes {{ count($ayes) }}, Noes {{ count($noes) }})<span class="sr-only"> in the vote of {{ $division->sitting_date->format('j F Y') }}</span></summary>
                                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <h4 class="font-semibold">Ayes ({{ count($ayes) }})</h4>
                                        <p class="mt-1 leading-[21px] text-ink-muted">{{ implode(', ', $ayes) }}</p>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold">Noes ({{ count($noes) }})</h4>
                                        <p class="mt-1 leading-[21px] text-ink-muted">{{ implode(', ', $noes) }}</p>
                                    </div>
                                </div>
                            </details>
                            @if ($division->proceedingsDocument->sourceUrl())
                                <p class="text-ink-muted">
                                    <a href="{{ $division->proceedingsDocument->sourceUrl() }}" class="link" rel="noopener">Official record: {{ $division->proceedingsDocument->title }} <span aria-hidden="true">↗</span></a>
                                    <span>({{ $division->reference() }})</span>
                                </p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </section>

            @if ($sources !== [])
                <section aria-labelledby="sources" class="{{ $sectionClass }}">
                    <h2 id="sources" class="{{ $headingClass }}">Sources</h2>
                    <ul class="flex flex-col text-small leading-[21px]">
                        @foreach ($sources as $source)
                            <li class="break-words border-t border-rule py-2 first:border-t-0 first:pt-0">
                                @if ($source['url'])
                                    <a href="{{ $source['url'] }}" class="link" rel="noopener">{{ $source['label'] }}</a>
                                @else
                                    <span class="text-ink-muted">{{ $source['label'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @unless ($isPreview)
                <p class="flex h-13 items-center justify-between gap-4 border-y border-rule text-small leading-[18px] lg:hidden">
                    Spotted a mistake?
                    <a href="{{ $reportUrl }}" class="font-medium underline underline-offset-4">Report a problem</a>
                </p>
            @endunless
        </article>

        <aside class="hidden flex-col gap-8 lg:flex lg:w-80 lg:shrink-0" aria-label="About this question">
            <nav aria-labelledby="on-this-page" class="flex flex-col">
                <h2 id="on-this-page" class="eyebrow border-b border-rule pb-3">On this page</h2>
                <ul>
                    @foreach ($sections as $id => $label)
                        <li>
                            <a href="#{{ $id }}" class="flex min-h-9 items-center border-l-2 border-rule py-2 pl-3 text-small leading-[18px] text-ink-muted hover:border-ink hover:text-ink">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            @unless ($isPreview)
                <div class="flex flex-col gap-1.5 border-t border-rule pt-4 text-small leading-[18px]">
                    <p class="font-semibold">Spotted a mistake?</p>
                    <p class="leading-[21px] text-ink-muted">Tell us and we'll check it against the official record.</p>
                    <p><a href="{{ $reportUrl }}" class="underline underline-offset-4">Report a problem with this question <span aria-hidden="true">&rarr;</span></a></p>
                </div>

                <div class="flex flex-col gap-3 rounded-md bg-surface p-5">
                    <p class="text-small font-semibold leading-[18px]">Answer them yourself</p>
                    <p class="text-small leading-[21px] text-ink-muted">See which parties voted the way you would have.</p>
                    <a href="{{ route('home') }}" class="flex h-11 items-center justify-center rounded-md bg-ink text-small font-medium text-ground hover:opacity-85">Take the quiz</a>
                </div>
            @endunless
        </aside>
    </div>
</x-layouts.public>
