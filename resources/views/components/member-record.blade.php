@props(['record', 'policyCount', 'role', 'compact' => false])

@php
    $seat = $record['seat'];
    $member = $seat->member;
    $partyName = $seat->party->display_name ?? $seat->party->name;
    $shown = 3;
    $chevron = '<svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true" class="shrink-0 text-ink-muted transition-transform group-open:rotate-180 motion-reduce:transition-none"><path d="M2.5 4L6 7.5L9.5 4" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>';
@endphp

{{--
    One MP and their record on each published question. The full card is for
    the district's MLA; the compact row, which opens to show the record, is
    for the region's MLCs. Every MP is drawn the same way, whatever their party.
--}}

@php
    /** One question and the MP's stance on it; everything is escaped. */
    $stanceRow = function (array $row): string {
        ['policy' => $policy, 'stance' => $stance] = $row;

        return '<li class="flex flex-col gap-0.5 border-t border-rule py-2 text-[13px] leading-[19px] sm:flex-row sm:justify-between sm:gap-6 sm:py-2.5 lg:text-small lg:leading-[18px]">'
            .'<a href="'.e(route('policies.show', $policy->slug)).'" class="link">'.e($policy->question).'</a>'
            .'<span class="shrink-0 text-ink-muted sm:w-40 sm:text-right">'.e($stance?->text() ?? '— No vote recorded').'</span>'
            .'</li>';
    };
@endphp

@if ($compact)
    <article aria-labelledby="member-{{ $member->slug }}" class="border-t border-rule first:border-t-0">
        @if ($policyCount === 0)
            <div class="flex min-h-14 flex-col justify-center gap-0.5 py-2.5 lg:min-h-15">
                <h3 id="member-{{ $member->slug }}" class="text-[15px] font-medium leading-[18px] lg:text-base lg:leading-5">{{ $member->display_name }}</h3>
                <p class="flex items-center gap-1.5 text-label text-ink-muted lg:gap-2 lg:text-[13px] lg:leading-4"><x-party-swatch :code="$seat->party->short_name" />{{ $partyName }}</p>
            </div>
        @else
            <details class="group">
                <summary class="grid min-h-14 cursor-pointer list-none grid-cols-[1fr_auto] items-center gap-x-2.5 gap-y-0.5 py-2.5 lg:min-h-15 lg:gap-x-4 [&::-webkit-details-marker]:hidden">
                    <h3 id="member-{{ $member->slug }}" class="text-[15px] font-medium leading-[18px] lg:text-base lg:leading-5">{{ $member->display_name }}</h3>
                    <span class="row-span-2 flex items-center gap-4 text-small leading-[18px]">
                        <span class="hidden lg:inline">See record<span class="sr-only"> of {{ $member->display_name }}</span></span>
                        {!! $chevron !!}
                    </span>
                    <span class="flex items-center gap-1.5 text-label text-ink-muted lg:gap-2 lg:text-[13px] lg:leading-4"><x-party-swatch :code="$seat->party->short_name" />{{ $partyName }} <span class="sr-only">· {{ $role }}</span></span>
                </summary>
                <div class="flex flex-col pb-4">
                    <p class="pb-2 text-[13px] font-semibold leading-4 lg:text-small lg:leading-[18px]">Voted on {{ $record['voted'] }} of the {{ $policyCount }} {{ Str::plural('question', $policyCount) }}</p>
                    <ul class="flex flex-col">
                        @foreach ($record['stances'] as $row)
                            {!! $stanceRow($row) !!}
                        @endforeach
                    </ul>
                    <p class="pt-2 text-label text-ink-muted lg:text-[13px] lg:leading-4">Some questions were voted on in one house only.</p>
                    @if ($member->profile_url)
                        <p class="pt-3 text-[13px] leading-4 lg:text-small lg:leading-[18px]"><a href="{{ $member->profile_url }}" class="link text-ink-muted" rel="noopener">Parliament profile<span class="sr-only"> of {{ $member->display_name }}</span> <span aria-hidden="true">↗</span></a></p>
                    @endif
                </div>
            </details>
        @endif
    </article>
@else
    <article aria-labelledby="member-{{ $member->slug }}" class="flex flex-col rounded-md border border-rule-strong">
        <div class="flex flex-col gap-2 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:gap-4 lg:px-6 lg:py-5">
            <div class="flex flex-col gap-1">
                <h3 id="member-{{ $member->slug }}" class="text-body font-semibold leading-[22px] lg:text-[18px]">{{ $member->display_name }}</h3>
                <p class="flex items-center gap-2 text-[13px] leading-4 text-ink-muted lg:text-small lg:leading-[18px]"><x-party-swatch :code="$seat->party->short_name" />{{ $partyName }} · {{ $role }}</p>
            </div>
            @if ($member->profile_url)
                <p class="text-[13px] leading-4 lg:text-small lg:leading-[18px]"><a href="{{ $member->profile_url }}" class="link text-ink-muted" rel="noopener">Parliament profile<span class="sr-only"> of {{ $member->display_name }}</span> <span aria-hidden="true">↗</span></a></p>
            @endif
        </div>

        <div class="flex flex-col rounded-b-md border-t border-rule bg-surface px-4 pb-3 pt-2.5 lg:px-6 lg:pb-4 lg:pt-3">
            @if ($policyCount === 0)
                <p class="py-1 text-[13px] leading-[19px] text-ink-muted lg:text-small lg:leading-[21px]">Their record will appear here once the questions are published.</p>
            @else
                <details open class="group">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 pb-1.5 pt-0 text-[13px] font-semibold leading-4 lg:pb-2 lg:pt-1 lg:text-small lg:leading-[18px] [&::-webkit-details-marker]:hidden">
                        Voted on {{ $record['voted'] }} of the {{ $policyCount }} {{ Str::plural('question', $policyCount) }}
                        <svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true" class="shrink-0 rotate-180 transition-transform group-open:rotate-0 motion-reduce:transition-none"><path d="M2.5 8L6 4.5L9.5 8" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>
                    </summary>
                    <ul class="flex flex-col">
                        @foreach (array_slice($record['stances'], 0, $shown) as $row)
                            {!! $stanceRow($row) !!}
                        @endforeach
                    </ul>
                    @if (count($record['stances']) > $shown)
                        <details class="group/more">
                            <summary class="cursor-pointer list-none pt-1.5 text-label text-ink-muted hover:text-ink group-open/more:hidden lg:pt-2 lg:text-[13px] lg:leading-4 [&::-webkit-details-marker]:hidden">
                                + {{ count($record['stances']) - $shown }} more {{ Str::plural('question', count($record['stances']) - $shown) }}
                            </summary>
                            <ul class="flex flex-col">
                                @foreach (array_slice($record['stances'], $shown) as $row)
                                    {!! $stanceRow($row) !!}
                                @endforeach
                            </ul>
                        </details>
                    @endif
                    <p class="pt-2 text-label text-ink-muted lg:text-[13px] lg:leading-4">Some questions were voted on in one house only.</p>
                </details>
            @endif
        </div>
    </article>
@endif
