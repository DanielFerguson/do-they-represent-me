@php($districtCount = $regions->sum(fn ($region) => $region->districts->count()))

<x-layouts.public page-type="districts_index" share-image="images/share-district.png" title="Districts" description="Find your Victorian state electoral district and see how your MPs voted.">
    <div class="mx-auto flex max-w-page flex-col gap-6 px-5 pb-12 pt-5 lg:flex-row lg:items-start lg:gap-24 lg:pb-24 lg:pt-18">
        <div class="flex min-w-0 flex-col gap-6 lg:w-[704px] lg:shrink-0 lg:gap-12">
            <header class="flex flex-col gap-2.5 lg:gap-4">
                @if ($districtCount > 0)
                    <p class="eyebrow">{{ $districtCount }} {{ Str::plural('district', $districtCount) }} · {{ $regions->count() }} {{ Str::plural('region', $regions->count()) }}</p>
                @endif
                <h1 class="text-h1-mobile font-semibold tracking-display lg:text-h1">Find your district</h1>
                <p class="text-[15px] leading-[23px] text-ink-muted lg:text-body">
                    <span class="lg:hidden">See your six MPs, one for your district and five for your region, and how they voted.</span>
                    <span class="hidden lg:inline">See who represents you in both houses of State Parliament and how they voted on each question.</span>
                </p>
            </header>

            <x-finder :url="$localitiesUrl" />

            <div id="regions" class="flex scroll-mt-6 flex-col gap-6 lg:gap-12">
                @foreach ($regions as $region)
                    <section aria-labelledby="region-{{ $region->slug }}" class="flex flex-col gap-2.5 border-t border-ink pt-3.5 lg:gap-3 lg:pt-4">
                        <div class="flex items-baseline justify-between gap-4">
                            <h2 id="region-{{ $region->slug }}" class="text-body font-semibold leading-6 lg:text-h2 lg:leading-7">{{ $region->name }} Region</h2>
                            <span class="shrink-0 text-label text-ink-muted lg:text-[13px] lg:leading-4">{{ $region->districts->count() }} {{ Str::plural('district', $region->districts->count()) }}</span>
                        </div>
                        <ul class="grid grid-cols-2 gap-x-4 gap-y-2.5 lg:grid-cols-3">
                            @foreach ($region->districts as $district)
                                <li><a href="{{ route('districts.show', $district->slug) }}" class="link text-[15px] leading-[18px]">{{ $district->name }}</a></li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        </div>

        <aside class="flex flex-col gap-8 lg:w-80 lg:shrink-0" aria-label="About your MPs">
            <div class="hidden flex-col lg:flex">
                <h2 class="eyebrow border-b border-rule pb-3">You have six MPs</h2>
                <ul class="text-small leading-[21px] text-ink-muted">
                    <li class="flex gap-4 border-b border-rule py-3"><span class="w-6 shrink-0 font-semibold text-ink">1</span> Legislative Assembly. One member for your district.</li>
                    <li class="flex gap-4 border-b border-rule py-3"><span class="w-6 shrink-0 font-semibold text-ink">5</span> Legislative Council. Five members for your region.</li>
                </ul>
            </div>
            <div class="flex flex-col gap-1.5 rounded-md bg-surface p-4 lg:gap-2 lg:p-5">
                <p class="text-small font-semibold leading-[18px]">Kept on this device only</p>
                <p class="text-[13px] leading-[19px] text-ink-muted lg:text-small lg:leading-[21px]">If you choose a district, we remember it in your browser so your results can show your MPs.<span class="hidden lg:inline"> We never see it.</span></p>
            </div>
        </aside>
    </div>
</x-layouts.public>
