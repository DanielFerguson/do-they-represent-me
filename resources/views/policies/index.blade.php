<x-layouts.public title="Questions">
    <div class="mx-auto flex max-w-3xl flex-col gap-10 px-4 py-10">
        <header class="flex flex-col gap-3">
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">The questions</h1>
            <p class="text-zinc-600 dark:text-zinc-400">
                Each question in the quiz is linked to real votes in the Parliament of Victoria. Choose one to see those votes, how each party voted, and what each side said.
                <a href="{{ route('methodology') }}" class="underline underline-offset-4">How the questions were chosen</a>
            </p>
        </header>

        @if ($topics->isEmpty())
            <p role="status" class="rounded-md border border-dashed border-zinc-300 px-4 py-3 text-zinc-600 dark:border-zinc-700 dark:text-zinc-400">
                The questions are being checked by reviewers and will be published here soon.
            </p>
        @else
            @foreach ($topics as $topic => $policies)
                <section aria-labelledby="topic-{{ $loop->index }}" class="flex flex-col gap-3">
                    <h2 id="topic-{{ $loop->index }}" class="text-lg font-semibold">{{ $topic }}</h2>
                    <ul class="flex flex-col divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                        @foreach ($policies as $policy)
                            <li>
                                <a href="{{ route('policies.show', $policy->slug) }}" class="flex flex-col gap-1 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-900">
                                    <span class="font-medium">{{ $policy->question }}</span>
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $policy->title }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        @endif
    </div>
</x-layouts.public>
