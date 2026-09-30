@props(['candidates', 'label'])

<ol aria-label="{{ $label }}" class="flex flex-col text-[13px] leading-[19px] lg:text-small lg:leading-[18px]">
    @foreach ($candidates as $candidate)
        <li class="flex flex-col gap-0.5 border-t border-rule py-2.5 first:border-t-0 sm:flex-row sm:items-baseline sm:justify-between sm:gap-6">
            <span class="font-medium">
                @if ($candidate->ballot_group)
                    <span class="font-normal text-ink-muted">Group {{ $candidate->ballot_group }}:</span>
                @endif
                {{ $candidate->ballotName() }}
            </span>
            <span class="flex items-center gap-2 text-ink-muted sm:text-right">
                @if ($candidate->party)
                    <x-party-swatch :code="$candidate->party->short_name" />
                @endif
                <span>
                    {{ $candidate->ballot_party ?? 'Independent' }}
                    @if ($candidate->member)
                        · MP in the 60th Parliament
                    @endif
                </span>
            </span>
        </li>
    @endforeach
</ol>
