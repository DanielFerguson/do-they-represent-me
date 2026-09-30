<x-layouts.public>
    <div class="mx-auto flex max-w-2xl flex-col gap-12 px-4 py-16">
        <div class="flex flex-col gap-4">
            <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">How do Victoria's parties and MPs actually vote?</h1>
            <p class="text-lg text-zinc-600 dark:text-zinc-400">
                Answer a short set of questions about issues Parliament has voted on, then see which parties and MPs voted the way you would have — based on the record, not the rhetoric.
            </p>
            <div>
                <a href="{{ route('quiz') }}" class="inline-flex items-center rounded-md bg-zinc-900 px-5 py-3 font-medium text-white hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-300">Start the quiz</a>
            </div>
        </div>

        @if ($isSample)
            <x-sample-notice />
        @endif

        <section aria-labelledby="find" class="flex flex-col gap-3">
            <div class="flex flex-col gap-1">
                <h2 id="find" class="text-lg font-semibold">Find your MPs</h2>
                <p class="text-zinc-600 dark:text-zinc-400">See who represents your area, and your results will include them.</p>
            </div>
            <x-finder :url="$localitiesUrl" />
        </section>

        <section aria-labelledby="how" class="flex flex-col gap-3">
            <h2 id="how" class="text-lg font-semibold">How it works</h2>
            <ol class="flex list-decimal flex-col gap-2 pl-5 text-zinc-700 dark:text-zinc-300">
                <li>Each question is linked to real votes (divisions) in the Legislative Assembly and Legislative Council since December 2022.</li>
                <li>You say whether you agree, disagree or aren't sure. Skip anything you like.</li>
                <li>We compare your answers with how each party and your own MPs voted, and show you <a href="{{ route('policies.index') }}" class="underline underline-offset-4">the evidence behind every question</a>.</li>
            </ol>
            <p class="text-sm text-zinc-600 dark:text-zinc-400"><a href="{{ route('methodology') }}" class="underline underline-offset-4">Read how it works in detail</a>.</p>
        </section>
    </div>
</x-layouts.public>
