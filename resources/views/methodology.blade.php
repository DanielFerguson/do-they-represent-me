<x-layouts.public title="Methodology" description="How Do They Represent Me? turns the Parliament of Victoria's voting records into quiz results.">
    <div class="mx-auto flex max-w-2xl flex-col gap-8 px-4 py-10">
        <header class="flex flex-col gap-3">
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">How it works</h1>
            <p class="text-lg text-zinc-600 dark:text-zinc-400">
                This site compares your views with how Victoria's parties and MPs voted in State Parliament. Here is exactly how, so you can check the work.
            </p>
        </header>

        <x-prose>
            <h2 id="sources">Where the votes come from</h2>
            <p>
                Every vote comes from the official record of the 60th Parliament of Victoria, which first sat in December 2022: the Legislative Assembly's <em>Votes and Proceedings</em> and the Legislative Council's <em>Minutes of the Proceedings</em>. These list, for every division (a formal counted vote), the question put and the name of every member who voted Aye or No.
            </p>
            <p>
                The site holds {{ number_format($divisions) }} divisions and {{ number_format($votes) }} individual votes{{ $dataAsOf ? ', up to '.$dataAsOf->format('j F Y') : '' }}. Each division's recorded names are checked against the totals printed in the record, and every name is matched to the member who held that seat on that day, with their party on that day. Divisions are read from the documents automatically, then checked daily.
            </p>

            <h2 id="questions">How the questions were chosen</h2>
            <p>
                The questions were chosen by following a written protocol, not by anyone deciding which policies are good. The project's owner stepped back from choosing and wording the questions so that their own views could not shape the results.
            </p>
            <p>
                <strong>The questions and the research behind them were drafted with AI</strong> (Anthropic's Claude), working to that protocol. It:
            </p>
            <ul>
                <li>listed every bill and motion where the main parties voted differently, leaving out procedural votes and motions about individual MPs;</li>
                <li>scored each one on how prominent the issue was, how much the parties differed, how strong the voting record is, and how clearly it could be asked as a yes-or-no question;</li>
                <li>selected a balanced set across topics, so that no party is on the "agree" side of most questions, and "agree" sometimes means change and sometimes keeping things as they are;</li>
                <li>read the bill, the minister's speech and the main opposition and crossbench speeches for each, and confirmed which way each vote went from the official record;</li>
                <li>wrote each question in plain English, in 25 words or fewer, without party names or loaded words;</li>
                <li>reviewed each question from the point of view of a supporter of Labor, of the Coalition, of the Greens and of the crossbench, and of a reader with no political background, revising it until no objection was left unresolved.</li>
            </ul>
            <p>
                <strong>Every question is then checked by human reviewers of different political leanings before it is published.</strong> Only questions they approve appear in the quiz. The reviewers can reword, change or drop any question, and they write any note that replaces a party's or MP's figure (see below).
            </p>
            @if ($publishedPolicies > 0)
                <p>You can see <a href="{{ route('policies.index') }}">every published question and the votes behind it</a>.</p>
            @endif

            <h2 id="scores">How votes become a record</h2>
            <p>
                Each question is linked to one or more divisions, and each link says whether an Aye or a No vote matches "agree". The method follows the one used by <a href="https://theyvoteforyou.org.au/help/faq" rel="noopener">They Vote For You</a> for the federal Parliament, with the changes noted here.
            </p>
            <ul>
                <li><strong>Strong and normal votes.</strong> Votes on a bill's second or third reading, which decide whether it passes, count five times as much as other votes, such as amendments.</li>
                <li><strong>A party's position</strong> on a division is how most of its members who voted, voted, using each member's party on the day of the vote. A tie gives no position. Independents are never grouped together; each is counted on their own.</li>
                <li><strong>Free votes.</strong> Where members were free to vote as they chose, no party is given a position.</li>
                <li><strong>Missed votes are left out.</strong> Victoria's records do not show pairs (arrangements where an absent member is matched with one from the other side), so a missed vote might be a pair, an illness or a choice. Missed votes never count for or against anyone.</li>
            </ul>
            <p>
                The share of votes that went the "agree" way, weighted as above, gives a label from "Consistently for" (95% or more) through "Mixed" (40 to 60%) to "Consistently against" (under 5%). With fewer than two votes and no strong vote, the record is shown as "Not enough votes".
            </p>
            <p>
                <strong>Notes instead of figures.</strong> Occasionally the reviewers found that a figure would misstate a party's or MP's position, for example where they voted for a bill but against the part the question is about. There, the site shows the reviewers' note instead of a figure, and that party or MP is left out of your match on that question.
            </p>

            <h2 id="match">How your match is worked out</h2>
            <p>
                Your match is calculated in your own browser. Your answers are never sent to us. For each question you answer Agree or Disagree where a party or MP has a figure, the match is how close their record is to your answer. Your overall match is the average across those questions. Unsure and skipped questions are left out.
            </p>
            <ul>
                <li>You need at least 5 Agree or Disagree answers to see results.</li>
                <li>A party or MP is only compared with you if they have a record on at least 3 of the questions you answered.</li>
                <li>MPs are shown for the district you choose: your member of the Legislative Assembly and the five members of the Legislative Council for your region. Some questions were only voted on in one house.</li>
            </ul>

            <h2 id="limitations">Limitations</h2>
            <ul>
                <li>A small set of votes can't capture everything a party stands for. The questions cover issues that came to a vote in this Parliament, not every issue in the election.</li>
                <li>MPs mostly vote with their party, so an MP's record usually mirrors their party's.</li>
                <li>Some committee votes appear in Hansard but not in the official record used here, so they can't be linked.</li>
                <li>Parties and candidates without MPs in this Parliament have no voting record here.</li>
                <li>The suburb finder uses Australian Bureau of Statistics data and is approximate near district boundaries. The Victorian Electoral Commission can confirm your district for your exact address.</li>
            </ul>

            <h2 id="corrections">Corrections</h2>
            <p>
                If you think something here is wrong, please <a href="{{ route('about') }}#corrections">tell us</a>. Corrections are made openly, and the code and data are <a href="{{ config('site.repository_url') }}" rel="noopener">published on GitHub</a>.
            </p>
        </x-prose>
    </div>
</x-layouts.public>
