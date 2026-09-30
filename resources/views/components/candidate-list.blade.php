@props(['candidates', 'label'])

<ol aria-label="{{ $label }}" class="flex flex-col divide-y divide-zinc-100 text-sm dark:divide-zinc-900">
    @foreach ($candidates as $candidate)
        <li class="flex flex-col gap-0.5 py-2 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
            <span>
                @if ($candidate->ballot_group)
                    <span class="text-zinc-500">Group {{ $candidate->ballot_group }}:</span>
                @endif
                {{ $candidate->ballotName() }}
            </span>
            <span class="text-zinc-600 dark:text-zinc-400">
                {{ $candidate->ballot_party ?? 'Independent' }}
                @if ($candidate->member)
                    · MP in the 60th Parliament
                @endif
            </span>
        </li>
    @endforeach
</ol>
