<x-layouts.public :title="$district->name.' District'" :description="'The MLA and MLCs for '.$district->name.' District, and how they voted in the Parliament of Victoria.'">
    <div class="mx-auto flex max-w-3xl flex-col gap-10 px-4 py-10">
        <header class="flex flex-col gap-3">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                <a href="{{ route('districts.index') }}" class="underline-offset-4 hover:underline">Districts</a>
                <span aria-hidden="true">/</span>
                {{ $region?->name }} Region
            </p>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ $district->name }} District</h1>
            <p class="text-zinc-600 dark:text-zinc-400">
                Voters in {{ $district->name }} elect one member of the Legislative Assembly (the lower house). They also help elect the five members of the Legislative Council (the upper house) for {{ $region?->name }} Region.
            </p>
            <div
                x-data="myDistrict"
                data-district="{{ $district->slug }}"
                class="flex flex-wrap items-center gap-3"
            >
                <a href="{{ route('quiz') }}" class="inline-flex items-center rounded-md bg-zinc-900 px-5 py-3 font-medium text-white hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-300">Take the quiz</a>
                <button type="button" x-show="!isMine" x-cloak x-on:click="choose" class="inline-flex rounded-md border border-zinc-300 px-4 py-3 hover:border-zinc-500 dark:border-zinc-700">Use this as my district</button>
                <p x-show="isMine" x-cloak class="text-sm text-zinc-600 dark:text-zinc-400">This is your district. Your results will show these members.</p>
            </div>
        </header>

        <section aria-labelledby="assembly" class="flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <h2 id="assembly" class="text-lg font-semibold">Member of the Legislative Assembly</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">In the 60th Parliament (2022–2026).</p>
            </div>

            @if ($member)
                <x-member-record :record="$member" :policy-count="$policyCount" :role="'Member for '.$district->name" />
            @elseif ($vacancy)
                <p class="rounded-lg border border-zinc-200 p-4 text-zinc-700 dark:border-zinc-800 dark:text-zinc-300">
                    This seat is vacant. {{ $vacancy->member->display_name }} ({{ $vacancy->party->display_name ?? $vacancy->party->name }}) was the member until {{ $vacancy->ends_on?->format('j F Y') }}.
                </p>
            @else
                <p class="text-zinc-600 dark:text-zinc-400">No member is recorded for this district.</p>
            @endif
        </section>

        @if ($region)
            <section aria-labelledby="council" class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <h2 id="council" class="text-lg font-semibold">Members of the Legislative Council for {{ $region->name }} Region</h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Each region elects five members. In the 60th Parliament (2022–2026), in alphabetical order.</p>
                </div>

                <div class="flex flex-col gap-3">
                    @forelse ($regionMembers as $record)
                        <x-member-record :record="$record" :policy-count="$policyCount" :role="'Member for '.$region->name" />
                    @empty
                        <p class="text-zinc-600 dark:text-zinc-400">No members are recorded for this region.</p>
                    @endforelse
                </div>
            </section>
        @endif

        @if ($election && ($candidates->isNotEmpty() || $regionCandidates->isNotEmpty()))
            <section aria-labelledby="candidates" class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <h2 id="candidates" class="text-lg font-semibold">Candidates at the {{ $election->name }}</h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">In ballot paper order, as published by the Victorian Electoral Commission.</p>
                </div>

                @if ($candidates->isNotEmpty())
                    <x-candidate-list :candidates="$candidates" :label="$district->name.' District (Legislative Assembly)'" />
                @endif

                @if ($regionCandidates->isNotEmpty())
                    <details>
                        <summary class="cursor-pointer text-zinc-700 underline-offset-4 hover:underline dark:text-zinc-300">{{ $region?->name }} Region candidates (Legislative Council)</summary>
                        <div class="mt-3">
                            <x-candidate-list :candidates="$regionCandidates" :label="$region?->name.' Region (Legislative Council)'" />
                        </div>
                    </details>
                @endif
            </section>
        @endif

        @if ($localities->isNotEmpty())
            <section aria-labelledby="localities" class="flex flex-col gap-2">
                <h2 id="localities" class="text-lg font-semibold">Suburbs and localities</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    Places with residents in this district. Those marked "part" are split between districts; <a href="{{ config('site.vec_lookup_url') }}" class="underline underline-offset-4" rel="noopener">check your address with the VEC</a>.
                </p>
                <p class="text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">
                    @foreach ($localities as $locality)
                        {{ $locality->name }}@if ((float) $locality->pivot->share < 0.99) (part)@endif{{ $loop->last ? '' : ',' }}
                    @endforeach
                </p>
            </section>
        @endif
    </div>
</x-layouts.public>
