<x-layouts.public title="About and corrections" description="Who runs Do They Represent Me?, where the data comes from, and how to report a mistake.">
    <div class="mx-auto flex max-w-2xl flex-col gap-8 px-4 py-10">
        <header class="flex flex-col gap-3">
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">About</h1>
            <p class="text-lg text-zinc-600 dark:text-zinc-400">
                <em>Do They Represent Me?</em> shows how Victoria's parties and MPs voted in State Parliament, so you can compare their record with your own views before the 2026 state election.
            </p>
        </header>

        <x-prose>
            <h2>Who runs it</h2>
            <p>
                It is an independent project. It is not affiliated with the Parliament of Victoria, the Victorian Electoral Commission, any political party or any candidate, and it doesn't tell you how to vote.
            </p>
            @if (config('site.authorisation'))
                <p>{{ config('site.authorisation') }}</p>
            @endif

            <h2 id="corrections">Corrections and right of reply</h2>
            <p>
                If you think a vote, a question or a description is wrong or unfair, including if you are an MP, a party or a candidate, please tell us, with a link to the record if you can. Every report is checked against the official record, and corrections are made openly.
            </p>
            @if (config('site.contact_email'))
                <p>Email <a href="mailto:{{ config('site.contact_email') }}">{{ config('site.contact_email') }}</a>.</p>
            @endif

            <h2>Sources</h2>
            <ul>
                <li><strong>Votes:</strong> the Parliament of Victoria's <em>Votes and Proceedings</em> (Legislative Assembly) and <em>Minutes of the Proceedings</em> (Legislative Council), 60th Parliament. © Parliament of Victoria. Each vote links to the official document it came from.</li>
                <li><strong>Members and their parties:</strong> the Parliament of Victoria's member pages, with party changes, resignations and by-elections checked by hand.</li>
                <li><strong>Suburbs and districts:</strong> based on Australian Bureau of Statistics data (Australian Statistical Geography Standard, Edition 3, and 2021 Census mesh block counts), licensed under <a href="https://creativecommons.org/licenses/by/4.0/" rel="noopener">CC BY 4.0</a>. Approximate near boundaries; not the official electoral boundaries.</li>
                <li><strong>Candidates:</strong> the Victorian Electoral Commission's published candidate lists. © Victorian Electoral Commission, licensed under <a href="https://creativecommons.org/licenses/by/4.0/" rel="noopener">CC BY 4.0</a>.</li>
                <li><strong>Method:</strong> adapted from <a href="https://theyvoteforyou.org.au" rel="noopener">They Vote For You</a> by the OpenAustralia Foundation.</li>
            </ul>
            <p>See <a href="{{ route('methodology') }}">how it works</a> for the full method.</p>

            <h2>Open source</h2>
            <p>The code and the reference data are <a href="{{ config('site.repository_url') }}" rel="noopener">published on GitHub</a>, so anyone can check how the results are produced.</p>
        </x-prose>
    </div>
</x-layouts.public>
