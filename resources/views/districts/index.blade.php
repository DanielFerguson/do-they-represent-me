<x-layouts.public title="Districts" description="Find your Victorian state electoral district and see how your MPs voted.">
    <div class="mx-auto flex max-w-3xl flex-col gap-10 px-4 py-10">
        <header class="flex flex-col gap-3">
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Find your district</h1>
            <p class="text-zinc-600 dark:text-zinc-400">
                Victoria has 88 Assembly districts in 8 regions. Search for your suburb, or choose your district from the list.
            </p>
            <x-finder :url="$localitiesUrl" />
        </header>

        @foreach ($regions as $region)
            <section aria-labelledby="region-{{ $region->slug }}" class="flex flex-col gap-3">
                <h2 id="region-{{ $region->slug }}" class="text-lg font-semibold">{{ $region->name }} Region</h2>
                <ul class="grid grid-cols-2 gap-x-4 gap-y-1 sm:grid-cols-3">
                    @foreach ($region->districts as $district)
                        <li><a href="{{ route('districts.show', $district->slug) }}" class="underline-offset-4 hover:underline">{{ $district->name }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
</x-layouts.public>
