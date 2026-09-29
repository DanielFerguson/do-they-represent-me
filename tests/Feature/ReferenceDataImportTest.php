<?php

use App\Domain\VicParliament\ReferenceData\ReferenceDataImporter;
use App\Enums\ElectorateKind;
use App\Models\Electorate;
use App\Models\House;
use App\Models\Member;
use App\Models\Membership;
use Illuminate\Support\Carbon;

it('imports the curated reference data', function () {
    $this->artisan('vic:import-data')->assertSuccessful();

    expect(House::query()->pluck('seats', 'slug')->all())->toBe(['assembly' => 88, 'council' => 40])
        ->and(Electorate::query()->where('kind', ElectorateKind::District)->count())->toBe(88)
        ->and(Electorate::query()->where('kind', ElectorateKind::Region)->count())->toBe(8)
        ->and(Electorate::query()->where('kind', ElectorateKind::District)->whereNull('region_id')->count())->toBe(0);

    Electorate::query()->where('kind', ElectorateKind::Region)->withCount('districts')->get()
        ->each(fn (Electorate $region) => expect($region->districts_count)->toBe(11, "{$region->name} should contain 11 districts"));
});

it('never seats more members than a house has seats', function (string $date) {
    $this->artisan('vic:import-data');

    House::query()->get()->each(function (House $house) use ($date) {
        $seated = Membership::query()->where('house_id', $house->id)->activeOn(Carbon::parse($date))->count();

        expect($seated)->toBeLessThanOrEqual($house->seats);
    });
})->with(['2022-11-26', '2023-07-10', '2023-12-10', '2024-11-10', '2025-01-10', '2026-02-20', '2026-09-28']);

it('does not overlap two memberships for the same seat', function () {
    $this->artisan('vic:import-data');

    Membership::query()->where('house_id', House::query()->where('slug', 'assembly')->value('id'))->get()
        ->groupBy('electorate_id')
        ->each(function ($stints) {
            $sorted = $stints->sortBy('starts_on')->values();

            foreach ($sorted as $index => $stint) {
                if ($index > 0) {
                    expect($stint->starts_on->gt($sorted[$index - 1]->ends_on))->toBeTrue();
                }
            }
        });
});

it('is idempotent', function () {
    $this->artisan('vic:import-data');
    $before = [Member::query()->count(), Membership::query()->pluck('id')->sort()->values()->all()];

    $this->artisan('vic:import-data');

    expect([Member::query()->count(), Membership::query()->pluck('id')->sort()->values()->all()])->toBe($before);
});

it('reports the file and line of an unknown reference', function () {
    $directory = sys_get_temp_dir().'/reference-data-'.uniqid();
    mkdir($directory);

    foreach (glob(database_path('data').'/*.csv') as $file) {
        copy($file, $directory.'/'.basename($file));
    }

    file_put_contents($directory.'/memberships.csv', "member,house,electorate,party,starts_on,ends_on,start_reason,end_reason\nnobody,assembly,Preston,ALP,2022-11-26,,,\n");

    expect(fn () => app(ReferenceDataImporter::class)->import($directory))
        ->toThrow(RuntimeException::class, 'memberships.csv line 2: unknown member [nobody].');
});
