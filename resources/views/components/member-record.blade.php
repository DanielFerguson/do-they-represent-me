@props(['record', 'policyCount', 'role'])

@php($seat = $record['seat'])

<article class="flex flex-col gap-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-800" aria-labelledby="member-{{ $seat->member->slug }}">
    <div class="flex flex-col gap-0.5">
        <h3 id="member-{{ $seat->member->slug }}" class="font-medium">{{ $seat->member->display_name }}</h3>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $seat->party->display_name ?? $seat->party->name }} · {{ $role }}</p>
    </div>

    @if ($policyCount === 0)
        <p class="text-sm text-zinc-600 dark:text-zinc-400">Their record will appear here once the questions are published.</p>
    @else
        <details class="text-sm">
            <summary class="cursor-pointer text-zinc-700 underline-offset-4 hover:underline dark:text-zinc-300">
                Voted on {{ $record['voted'] }} of the {{ $policyCount }} {{ Str::plural('question', $policyCount) }} <span class="text-zinc-500">(some were voted on in one house only)</span>
            </summary>
            <ul class="mt-3 flex flex-col divide-y divide-zinc-100 dark:divide-zinc-900">
                @foreach ($record['stances'] as ['policy' => $policy, 'stance' => $stance])
                    <li class="flex flex-col gap-0.5 py-2 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                        <a href="{{ route('policies.show', $policy->slug) }}" class="underline-offset-4 hover:underline">{{ $policy->question }}</a>
                        <span class="shrink-0 text-zinc-600 sm:text-right dark:text-zinc-400">{{ $stance?->text() ?? 'No vote recorded' }}</span>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif

    @if ($seat->member->profile_url)
        <p class="text-sm"><a href="{{ $seat->member->profile_url }}" class="text-zinc-600 underline underline-offset-4 dark:text-zinc-400" rel="noopener">Parliament profile</a></p>
    @endif
</article>
