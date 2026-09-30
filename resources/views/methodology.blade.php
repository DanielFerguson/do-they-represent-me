@php
    $sections = [
        'sources' => 'Where the votes come from',
        'questions' => 'How the questions were chosen',
        'positions' => "A party's position",
        'match' => "How you're matched",
        'not-counted' => "What isn't counted",
        'check' => 'Check our work',
    ];
    $labels = [
        'Consistently for · 95%+',
        'Almost always for · 85%+',
        'Generally for · 60%+',
        'Mixed · 40%+',
        'Generally against · 15%+',
        'Almost always against · 5%+',
        'Consistently against',
    ];
    $thresholds = [
        ['figure' => '5', 'text' => 'agree or disagree answers needed before we show results'],
        ['figure' => '3', 'text' => 'shared questions needed before a party is ranked'],
        ['figure' => 'A–Z', 'text' => "party order wherever a ranking isn't the point"],
    ];
    $dataVersion = array_filter([
        'Votes up to' => $dataAsOf?->format('j F Y'),
        'Questions' => $publishedPolicies > 0 ? (string) $publishedPolicies : null,
    ]);
@endphp

<x-layouts.public title="Methodology" description="How Do They Represent Me? turns the Parliament of Victoria's voting records into quiz results.">
    <div class="mx-auto flex max-w-page flex-col px-5 pb-12 pt-8 lg:flex-row lg:items-start lg:gap-24 lg:pb-24 lg:pt-18">
        <div class="flex min-w-0 flex-col gap-6 lg:w-[704px] lg:shrink-0 lg:gap-12">
            <x-page-header eyebrow="Methodology" title="How we got the answers">
                Every result on this site can be traced back to a vote in the official record. This page explains each step, written so anyone can check the work.
            </x-page-header>

            <x-on-this-page :sections="$sections" disclosure />

            <x-info-section id="sources" number="01" title="Where the votes come from" first>
                <x-prose>
                    <p>
                        Every recorded vote (a “division”) in both houses of the 60th Parliament, from its first sitting in December 2022 until it expires before the election. We read them from the Parliament's own weekly record: the Legislative Assembly's <em>Votes and Proceedings</em> and the Legislative Council's <em>Minutes of the Proceedings</em>.
                    </p>
                    <p>
                        The site holds {{ number_format($divisions) }} divisions and {{ number_format($votes) }} individual votes{{ $dataAsOf ? ', up to '.$dataAsOf->format('j F Y') : '' }}. Each division's names are checked against the totals printed in the record.
                    </p>
                    <p class="text-ink-muted">Each MP is matched to their party on the day of each vote, so members who changed party are counted correctly.</p>
                </x-prose>
            </x-info-section>

            <x-info-section id="questions" number="02" title="How the questions were chosen">
                <x-prose>
                    <p>
                        By a written, rule-based process, not anyone's opinion of which policies are good. The site's founder has political views of his own, so the questions were drafted with AI following that process, and every step was recorded so it can be checked. AI has leanings too, but its work is easier to monitor and verify than one person's judgement.
                    </p>
                </x-prose>
                <dl class="flex flex-col">
                    <x-fact term="Every contested vote">Bills and motions where the main parties split. Procedural business, motions about individual MPs and praise-or-censure motions are excluded.</x-fact>
                    <x-fact term="Scored, then balanced">Rated on public interest, clarity and strength of record. Chosen so each major party is on the “agree” side of 35–65% of questions.</x-fact>
                    <x-fact term="Plainly worded">One idea, 25 words or fewer, no party names, no loaded terms. Each vote's direction confirmed from the source.</x-fact>
                    <x-fact term="Reviewed from every side">AI reviewers argued from the view of each major party and the crossbench, and a plain-language reader checked the wording. Every question is then checked by human reviewers of different political leanings, who make the final call.</x-fact>
                </dl>
                @if ($publishedPolicies > 0)
                    <x-prose>
                        <p>You can read <a href="{{ route('policies.index') }}">every published question and the votes behind it</a>.</p>
                    </x-prose>
                @endif
            </x-info-section>

            <x-info-section id="positions" number="03" title="How a party's position is worked out">
                <x-prose>
                    <p>
                        On each vote, a party takes the side most of its MPs voted for, using each MP's party on the day. Across a question's linked votes, we count how often that side matched “agree”. Second and third readings, the votes that pass or reject a bill, count five times as much as other votes.
                    </p>
                </x-prose>
                <ul class="flex flex-wrap gap-1.5 lg:gap-2" aria-label="How agreement is labelled">
                    @foreach ($labels as $label)
                        <li class="rounded-sm border border-rule-strong px-2 py-[3px] text-label lg:px-2.5 lg:py-1 lg:text-[13px]">{{ $label }}</li>
                    @endforeach
                </ul>
                <p class="text-small leading-[21px] text-ink-muted">
                    This follows the method used by <a href="https://theyvoteforyou.org.au/help/faq" rel="noopener" class="link">They Vote For You</a> for the federal Parliament, with the changes below for Victoria's records.
                </p>
            </x-info-section>

            <x-info-section id="match" number="04" title="How you're matched">
                <x-prose>
                    <p>
                        In your browser, not on our servers. For each question you agree or disagree with, we compare your answer with each party's position, then average across your answers. “Unsure” and skipped questions aren't counted.
                    </p>
                </x-prose>
                <ul class="flex gap-2 lg:gap-3">
                    @foreach ($thresholds as $threshold)
                        <li class="flex flex-1 flex-col gap-0.5 rounded-md bg-surface p-3 lg:gap-1 lg:p-4">
                            <span class="text-[22px] font-semibold leading-7 lg:text-h1-mobile lg:leading-8">{{ $threshold['figure'] }}</span>
                            <span class="text-label text-ink-muted lg:text-small">{{ $threshold['text'] }}</span>
                        </li>
                    @endforeach
                </ul>
                <x-prose>
                    <p class="text-ink-muted">MPs are shown for the district you choose: your member of the Legislative Assembly and the five members of the Legislative Council for your region.</p>
                </x-prose>
            </x-info-section>

            <x-info-section id="not-counted" number="05" title="What isn't counted, and why">
                <dl class="flex flex-col">
                    <x-fact term="Absences">Victoria doesn't record pairs, so a missed vote may be illness, a pair or a choice. We show “did not vote” but never score it.</x-fact>
                    <x-fact term="Too few votes">With no second or third reading vote and fewer than two other votes, there's too little to state a position, so no figure is given.</x-fact>
                    <x-fact term="Free votes">On conscience votes no party has a position; MPs are compared one by one.</x-fact>
                    <x-fact term="Independents">Never treated as a group. Each is compared on their own votes.</x-fact>
                    <x-fact term="Display notes">Where reviewers found a figure would misstate a position, we show their note instead and leave that party out on that question.</x-fact>
                </dl>
                <x-prose>
                    <h3>Known limits</h3>
                    <ul>
                        <li>A small set of votes can't capture everything a party stands for. The questions cover issues that came to a vote in this Parliament, not every issue in the election.</li>
                        <li>Parties and candidates without MPs in this Parliament have no voting record here.</li>
                        <li>Some committee votes appear in Hansard but not in the official record used here, so they can't be linked.</li>
                        <li>The suburb finder uses Australian Bureau of Statistics data and is approximate near district boundaries. The Victorian Electoral Commission can confirm your district for your exact address.</li>
                    </ul>
                </x-prose>
            </x-info-section>

            <x-info-section id="check" number="06" title="Check our work">
                <x-prose>
                    <p>The code is open source, every published version of the data is kept, and every question links to the official record of each vote.</p>
                </x-prose>
                <div class="lg:hidden">
                    @if ($dataVersion !== [] || $snapshotHash)
                        <div class="flex flex-col gap-1.5 rounded-md bg-surface p-3.5 lg:gap-2 lg:p-5">
                            <h2 class="hidden text-small font-semibold leading-[18px] lg:block">This version of the data</h2>
                            <dl class="flex flex-col gap-1.5 text-[13px] leading-4 lg:gap-2">
                                @foreach ($dataVersion as $term => $value)
                                    <div class="flex justify-between gap-4"><dt class="text-ink-muted">{{ $term }}</dt><dd>{{ $value }}</dd></div>
                                @endforeach
                                @if ($snapshotHash)
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-ink-muted">Snapshot</dt>
                                        <dd class="text-label"><a href="{{ route('stances.show', $snapshotHash) }}" title="{{ $snapshotHash }}" class="link">{{ substr($snapshotHash, 0, 4) }}…{{ substr($snapshotHash, -4) }}</a></dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    @endif
                </div>
                <p class="flex flex-wrap gap-x-5 gap-y-3 text-small lg:gap-x-6 lg:text-[15px] lg:leading-[18px]">
                    <a href="{{ config('site.repository_url') }}" rel="noopener" class="link">Source code on GitHub ↗</a>
                    <a href="{{ route('contact', ['topic' => 'correction']) }}" class="link">Report a problem →</a>
                </p>
            </x-info-section>
        </div>

        <aside class="hidden flex-col gap-8 lg:sticky lg:top-8 lg:flex lg:w-80 lg:shrink-0">
            <x-on-this-page :sections="$sections" />
            @if ($dataVersion !== [] || $snapshotHash)
                <div class="flex flex-col gap-1.5 rounded-md bg-surface p-3.5 lg:gap-2 lg:p-5">
                    <h2 class="hidden text-small font-semibold leading-[18px] lg:block">This version of the data</h2>
                    <dl class="flex flex-col gap-1.5 text-[13px] leading-4 lg:gap-2">
                        @foreach ($dataVersion as $term => $value)
                            <div class="flex justify-between gap-4"><dt class="text-ink-muted">{{ $term }}</dt><dd>{{ $value }}</dd></div>
                        @endforeach
                        @if ($snapshotHash)
                            <div class="flex justify-between gap-4">
                                <dt class="text-ink-muted">Snapshot</dt>
                                <dd class="text-label"><a href="{{ route('stances.show', $snapshotHash) }}" title="{{ $snapshotHash }}" class="link">{{ substr($snapshotHash, 0, 4) }}…{{ substr($snapshotHash, -4) }}</a></dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif
        </aside>
    </div>
</x-layouts.public>
