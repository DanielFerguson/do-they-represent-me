@php
    $sections = [
        'sources' => 'Where the votes come from',
        'questions' => 'How the questions were chosen',
        'positions' => "A party's position",
        'match' => "How you're matched",
        'not-counted' => "What isn't counted",
        'check' => 'Check our work',
        'faq' => 'Common questions',
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
        ['figure' => '5', 'text' => 'yes or no answers needed before we show results'],
        ['figure' => '3', 'text' => 'shared questions needed before a party is ranked'],
        ['figure' => 'A–Z', 'text' => "party order wherever a ranking isn't the point"],
    ];
    $dataVersion = array_filter([
        'Votes up to' => $dataAsOf?->format('j F Y'),
        'Questions' => $publishedPolicies > 0 ? (string) $publishedPolicies : null,
    ]);

    $link = fn (string $url, string $text): string => '<a href="'.e($url).'">'.e($text).'</a>';
    $section = fn (string $id, string $text): string => $link(route('methodology').'#'.$id, $text);
    $questionsPhrase = $publishedPolicies.' '.Str::plural('question', $publishedPolicies);
    $agreeSideFigures = $publishedPolicies > 0 && $agreeSides->isNotEmpty()
        ? ' Of the '.$questionsPhrase.', the “yes” side includes '.e($agreeSides->map(fn ($party) => $party->display_name.' '.$party->agree_side_count)->join(', ', ' and ')).'.'
        : '';

    // Short answers to the concerns people are likely to have. The same text
    // is the page's FAQ for search engines, so the two can never differ.
    $commonQuestions = [
        'Bias and trust' => [
            'who-chose' => [
                'question' => 'Who chose these questions?',
                'answer' => "<p>Not Dan Ferguson, who built the site. Like anyone, he has political views, so he didn't choose or word the questions himself. AI drafted them by following written rules: start from every bill and motion where the main parties voted differently, score each one on public interest, clarity and strength of record, then balance the set across topics and parties.</p>"
                    ."<p>AI reviewers arguing for Labor, the Coalition, the Greens and the crossbench then challenged every question. A plain-language reader checked that it made sense, and a fact-checker checked every vote against the official record. Those reviewers made the final call. See ".$section('questions', 'how the questions were chosen').'.</p>',
            ],
            'why-ai' => [
                'question' => "Why use AI? Doesn't AI have biases too?",
                'answer' => "<p>Yes, AI has leanings too. But an AI working to written rules can be checked against those rules, challenged from every side and re-run, which is hard to do with one person's judgement.</p>"
                    ."<p>AI chose the questions and linked each one to the votes that answer it. From there, every party's and MP's position comes from the official record by a fixed formula, the same for everyone. See ".$section('positions', "how a party's position is worked out").'.</p>',
            ],
            'is-it-slanted' => [
                'question' => 'My party seems to come out badly. Is the quiz slanted?',
                'answer' => '<p>The questions were chosen so that no party is on the “yes” side of every question, or of none.'.$agreeSideFigures.' Some questions ask about keeping a law and others about changing one. If a result surprises you, '.$link(route('policies.index'), 'open that question').' to see each vote behind it.</p>',
            ],
            'how-to-vote' => [
                'question' => 'Does this site tell me how to vote?',
                'answer' => "<p>No. It shows how parties and MPs voted on the questions you choose to answer. It doesn't recommend anyone, and it isn't affiliated with any party, candidate or the Parliament of Victoria. Parties are listed A–Z unless you've asked for a ranking.</p>",
            ],
        ],
        "What's covered" => [
            'why-so-few' => [
                'question' => $publishedPolicies > 0 ? 'Why only '.$questionsPhrase.'?' : 'Why so few questions?',
                'answer' => '<p>A question needs three things: a recorded vote in this Parliament, the main parties voting differently, and a way to ask it as one fair yes-or-no question. Many bills pass without a recorded vote, and most votes are on amendments to individual clauses.</p>'
                    .'<p>The rules also preferred fewer questions with strong evidence over many with weak evidence, spread across 11 topic areas. Questions with too little voting record were dropped.</p>',
            ],
            'missing-issue' => [
                'question' => "Why isn't my issue in the quiz?",
                'answer' => '<p>Usually because Parliament never voted on it in a way that answers a clear question. For example:</p>'
                    .'<ul>'
                    .'<li>Legalising cannabis: the only vote was on holding a plebiscite.</li>'
                    .'<li>Rent caps: the only vote was on whether a bill could be introduced.</li>'
                    .'<li>A state debt limit, and regulating supermarket prices: one motion each, which is too little to show a position.</li>'
                    .'<li>Firearms: one vote, on a bill that bundled many separate changes.</li>'
                    .'</ul>'
                    .'<p>Many government decisions are also made without any vote in Parliament.</p>',
            ],
            'since-2022' => [
                'question' => 'Why only votes since December 2022?',
                'answer' => '<p>The site covers the current Parliament, the 60th, elected in November 2022: its MPs and their record over nearly four years. The government has been in office since 2014, but many MPs from earlier Parliaments have left, and parties may have changed their positions since. Adding earlier votes would also mean checking a second, older set of records.</p>',
            ],
            'missing-party' => [
                'question' => "Why isn't my party or candidate shown?",
                'answer' => "<p>Only parties with MPs in this Parliament, and those MPs, have a voting record. A new party, or a candidate who hasn't been an MP, has no record here, so we can't match you with them. That says nothing about their policies, so check their own platform. Independents are compared one by one.</p>",
            ],
        ],
        'Reading your results' => [
            'changed-position' => [
                'question' => 'What if a party has changed its position?',
                'answer' => "<p>Results reflect how parties voted in this Parliament, not their promises for 2026. A party may have changed its view since, or promised something different. Where a party voted different ways on a question, for example differently in each house, its result can show as Mixed. Check each party's own policies alongside your results.</p>",
            ],
            'reversing-a-law' => [
                'question' => 'Why do some questions ask about reversing a law?',
                'answer' => "<p>So that “yes” doesn't always mean siding with the government. Some questions ask whether to keep a law, and others whether to reverse one. When a question asks about reversing a law, a vote to pass that law counts as a “no”.</p>",
            ],
            'no-vote' => [
                'question' => 'Why does my MP have no vote on some questions?',
                'answer' => "<p>Some questions were voted on only in the Legislative Council, the upper house, so members of the Legislative Assembly show “No vote recorded”. “Did not vote” means the MP was absent. Victoria doesn't record pairs, so an absence may be illness, a pair or a choice. We show it, but never count it for or against anyone. See ".$section('not-counted', "what isn't counted").'.</p>',
            ],
            'note-not-figure' => [
                'question' => 'Why is there a note instead of a figure?',
                'answer' => '<p>Sometimes reviewers found that a figure would misstate a position, for example for a party that voted for a bill while saying it opposed the policy. Then we show their note instead, and leave that party or MP out of the match on that question.</p>',
            ],
        ],
        'Reporting and changes' => [
            'report-unfair' => [
                'question' => 'I think a question is unfair. What can I do?',
                'answer' => '<p>'.$link(route('contact', ['topic' => 'correction']), 'Tell us').', with a link to the record if you can. Every report is checked against the official record, whoever sends it, including MPs, parties and candidates. Corrections are made openly and usually take a few days.</p>',
            ],
            'will-it-change' => [
                'question' => 'Will the questions change before the election?',
                'answer' => "<p>No new votes will be added. Parliament isn't expected to sit again before the election, so the record is final. Questions can still be corrected if a report shows an error. Every published version of the data is kept, so changes can be checked.</p>",
            ],
        ],
    ];
    $faqQuestions = collect($commonQuestions)->flatten(1)->map(fn (array $item): array => [
        '@type' => 'Question',
        'name' => $item['question'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
    ])->values()->all();
@endphp

<x-layouts.public page-type="info" :schema-type="['WebPage', 'FAQPage']" :structured-data="['mainEntity' => $faqQuestions]" share-image="images/share-methodology.png" title="Methodology" description="How Do They Represent Me? turns the Parliament of Victoria's voting records into quiz results.">
    <div class="mx-auto flex max-w-page flex-col px-5 pb-12 pt-8 lg:flex-row lg:items-start lg:justify-between lg:gap-12 lg:pb-24 lg:pt-18">
        <div class="flex min-w-0 flex-col gap-6 lg:max-w-[704px] lg:flex-1 lg:gap-12">
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
                        By a written, rule-based process, not anyone's opinion of which policies are good. The site's founder has political views of his own, so the questions were drafted with AI following that process. AI has leanings too, but its work is easier to monitor and verify than one person's judgement.
                    </p>
                </x-prose>
                <dl class="flex flex-col">
                    <x-fact term="Every contested vote">Bills and motions where the main parties split. Procedural business, motions about individual MPs and praise-or-censure motions are excluded.</x-fact>
                    <x-fact term="Scored, then balanced">Rated on public interest, clarity and strength of record. Chosen so each major party is on the “yes” side of 35–65% of questions.</x-fact>
                    <x-fact term="Plainly worded">One idea, 25 words or fewer, no party names, no loaded terms. Each vote's direction confirmed from the source.</x-fact>
                    <x-fact term="Reviewed from every side">AI reviewers argued from the view of each major party and the crossbench, and a plain-language reader checked the wording. A final round of AI reviewers made the publication call on 30 September 2026: one for each of Labor, the Coalition, the Greens and the crossbench, a plain-language reader and a fact-checker, who checked every vote's direction against the official record. Where they disagreed, a question with too little voting record was dropped and a factual error was always fixed. Nobody's view of which policies are good was part of it. Anyone can report a problem, and every report is checked against the record.</x-fact>
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
                        On each vote, a party takes the side most of its MPs voted for, using each MP's party on the day. Across a question's linked votes, we count how often that side matched “yes”. Second and third readings, the votes that pass or reject a bill, count five times as much as other votes.
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
                        In your browser, not on our servers. For each question you answer yes or no, we compare your answer with each party's position, then average across your answers. “Unsure” and skipped questions aren't counted.
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

            <x-info-section id="faq" number="07" title="Common questions">
                <x-prose>
                    <p>Short answers to common concerns, with links to the detail above.</p>
                </x-prose>
                @foreach ($commonQuestions as $group => $questions)
                    <div class="flex flex-col gap-1.5 pt-2 lg:gap-2">
                        <h3 class="eyebrow">{{ $group }}</h3>
                        <div class="flex flex-col">
                            @foreach ($questions as $id => $item)
                                <details id="{{ $id }}" class="group scroll-mt-6 border-t border-rule last:border-b">
                                    <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-4 py-3 text-[15px] font-medium leading-[21px] [&::-webkit-details-marker]:hidden">
                                        {{ $item['question'] }}
                                        <svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true" class="shrink-0 group-open:rotate-180"><path d="M2.5 4L6 7.5L9.5 4" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>
                                    </summary>
                                    <x-prose class="pb-4">{!! $item['answer'] !!}</x-prose>
                                </details>
                            @endforeach
                        </div>
                    </div>
                @endforeach
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
