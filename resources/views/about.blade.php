@php
    $atAGlance = [
        ['term' => 'Built by', 'value' => 'Dan Ferguson'],
        ['term' => 'Funded by', 'value' => 'Self-funded'],
        ['term' => 'Affiliation', 'value' => 'None'],
        ['term' => 'Source code', 'value' => 'GitHub ↗', 'url' => config('site.repository_url')],
        ['term' => 'Data covers', 'value' => '60th Parliament'],
        ['term' => 'More about Dan', 'value' => 'danferg.com ↗', 'url' => 'https://danferg.com'],
    ];
    $promises = [
        "Every party is shown the same way. No party's colour stands alone, and lists are alphabetical unless you asked for a ranking.",
        'Every figure links back to the official record of each vote.',
        'Your answers never leave your device. No accounts, no ads, and no tracking of you: only anonymous counts of page views and button clicks.',
        "Mistakes are fixed openly. If you find one, tell us and we'll check it against the source.",
    ];
@endphp

<x-layouts.public page-type="info" share-image="images/share-methodology.png" title="About and corrections" description="Who runs Do They Represent Me?, where the data comes from, and how to report a mistake.">
    <div class="mx-auto flex max-w-page flex-col px-5 pb-12 pt-8 lg:flex-row lg:items-start lg:gap-24 lg:pb-24 lg:pt-18">
        <div class="flex min-w-0 flex-col gap-6 lg:w-[704px] lg:shrink-0 lg:gap-12">
            <x-page-header eyebrow="About" title="A voting record you can check, not a pitch">
                <em class="not-italic">Do They Represent Me?</em> shows how Victoria's parties and MPs actually voted in State Parliament, so you can compare their record with your own views before you vote on 28 November.
            </x-page-header>

            <dl class="flex flex-col rounded-md bg-surface px-4 py-1 text-[13px] leading-4 lg:hidden">
                @foreach ($atAGlance as $row)
                    <div class="flex h-10 items-center justify-between gap-4 border-rule not-first:border-t">
                        <dt class="text-ink-muted">{{ $row['term'] }}</dt>
                        <dd>
                            @isset($row['url'])
                                <a href="{{ $row['url'] }}" rel="noopener" class="underline decoration-1 underline-offset-[3px]">{{ $row['value'] }}</a>
                            @else
                                {{ $row['value'] }}
                            @endisset
                        </dd>
                    </div>
                @endforeach
            </dl>

            <x-info-section id="why" title="Why this exists" first>
                <x-prose large>
                    <p>
                        Election campaigns are about promises. Parliament's record is about what actually happened. Federal voters have <a href="https://theyvoteforyou.org.au" rel="noopener">They Vote For You</a>; Victorians had nothing like it. This project reads every recorded vote from the 60th Parliament and puts it next to plain questions you can answer yourself.
                    </p>
                </x-prose>
            </x-info-section>

            <x-info-section id="who" title="Who's behind it">
                <x-prose large>
                    <p>
                        Built by <a href="https://danferg.com" rel="noopener">Dan Ferguson</a>, a Victorian software developer. It's an independent, self-funded project with no grants, sponsors, donations or affiliates, and it isn't affiliated with any party, candidate, lobby group or the Parliament of Victoria. It doesn't tell you how to vote.
                    </p>
                    <p>
                        Like anyone, Dan has political views. So he didn't choose or word the questions himself. They were drafted with AI following a written, rule-based process, and challenged by AI reviewers arguing from each side. AI has political leanings too, but its work is far easier to monitor, check and re-run than one person's judgement. A final round of AI reviewers, arguing from each side and checking every fact against the official record, decided what was published. Dan stands behind that process, and anyone can report a problem. <a href="{{ route('methodology') }}#questions">How the questions were chosen</a>.
                    </p>
                </x-prose>
            </x-info-section>

            <x-info-section id="promises" title="What we promise">
                <ol class="flex flex-col">
                    @foreach ($promises as $promise)
                        <li class="flex gap-3 border-t border-rule py-2.5 text-small leading-[21px] last:border-b lg:gap-4 lg:py-3 lg:text-[15px] lg:leading-6">
                            <span class="w-4 shrink-0 text-[13px] font-semibold leading-4 lg:w-6 lg:text-small lg:leading-[18px]">{{ $loop->iteration }}</span>
                            {{ $promise }}
                        </li>
                    @endforeach
                </ol>
            </x-info-section>

            <x-info-section id="corrections" title="Corrections and right of reply">
                <x-prose>
                    <p>
                        If you think a vote, a question or a description is wrong or unfair, including if you are an MP, a party or a candidate, please <a href="{{ route('contact', ['topic' => 'correction']) }}">tell us</a>, with a link to the record if you can. Every report is checked against the official record, whoever sends it, and corrections are made openly. Corrections usually take a few days.
                    </p>
                </x-prose>
            </x-info-section>

            <x-info-section id="sources" title="Sources">
                <dl class="flex flex-col">
                    <x-fact term="Votes"><em>Votes and Proceedings</em> (Legislative Assembly) and <em>Minutes of the Proceedings</em> (Legislative Council), 60th Parliament. © Parliament of Victoria. Each vote links to the official document it came from.</x-fact>
                    <x-fact term="Members and parties">The Parliament of Victoria's member pages, with party changes, resignations and by-elections checked by hand.</x-fact>
                    <x-fact term="Suburbs and districts">Based on Australian Bureau of Statistics data (Australian Statistical Geography Standard, Edition 3, and 2021 Census mesh block counts), licensed under <a href="https://creativecommons.org/licenses/by/4.0/" rel="noopener" class="link">CC BY 4.0</a>. Approximate near boundaries; not the official electoral boundaries.</x-fact>
                    <x-fact term="Candidates">The Victorian Electoral Commission's published candidate lists. © Victorian Electoral Commission, licensed under <a href="https://creativecommons.org/licenses/by/4.0/" rel="noopener" class="link">CC BY 4.0</a>.</x-fact>
                    <x-fact term="Method">Adapted from <a href="https://theyvoteforyou.org.au" rel="noopener" class="link">They Vote For You</a> by the OpenAustralia Foundation. See the <a href="{{ route('methodology') }}" class="link">methodology</a> for the full method.</x-fact>
                </dl>
            </x-info-section>

            <a href="{{ route('contact') }}" class="flex h-12 items-center justify-center rounded-md border border-rule-strong text-[15px] font-medium hover:border-ink lg:hidden">Contact us</a>
        </div>

        <aside class="hidden flex-col gap-8 lg:flex lg:w-80 lg:shrink-0">
            <div class="flex flex-col">
                <h2 class="eyebrow border-b border-rule pb-3">At a glance</h2>
                <dl class="text-small leading-[18px]">
                    @foreach ($atAGlance as $row)
                        <div class="flex h-10 items-center justify-between gap-4 border-b border-rule">
                            <dt class="text-ink-muted">{{ $row['term'] }}</dt>
                            <dd>
                                @isset($row['url'])
                                    <a href="{{ $row['url'] }}" rel="noopener" class="underline decoration-1 underline-offset-4">{{ $row['value'] }}</a>
                                @else
                                    {{ $row['value'] }}
                                @endisset
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>
            <div class="flex flex-col gap-3 rounded-md bg-surface p-5 text-small">
                <h2 class="font-semibold leading-[18px]">Found a mistake, or a journalist?</h2>
                <p class="leading-[21px] text-ink-muted">We read every message.</p>
                <a href="{{ route('contact') }}" class="flex h-11 items-center justify-center rounded-md border border-rule-strong bg-ground font-medium leading-[18px] hover:border-ink">Contact us</a>
            </div>
        </aside>
    </div>
</x-layouts.public>
