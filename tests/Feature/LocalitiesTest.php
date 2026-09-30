<?php

use App\Domain\Localities\LocalityBuilder;
use App\Domain\Localities\LocalityDirectory;
use App\Domain\Stances\StanceSnapshots;
use App\Models\Electorate;
use App\Models\Locality;
use App\Models\Policy;
use Illuminate\Support\Facades\File;

/**
 * A scratch data folder holding the fixture electorates.csv, so the build
 * command can write localities.csv without touching database/data.
 */
function localityDataFolder(?string $electorates = null): string
{
    $folder = sys_get_temp_dir().'/localities-'.uniqid();
    File::ensureDirectoryExists($folder);
    File::put("{$folder}/electorates.csv", $electorates ?? File::get(base_path('tests/Fixtures/Localities/electorates.csv')));

    return $folder;
}

/**
 * @return list<array<string, string>>
 */
function builtLocalities(string $folder): array
{
    $lines = array_map('str_getcsv', file("{$folder}/localities.csv", FILE_IGNORE_NEW_LINES));
    $header = array_shift($lines);

    return array_map(fn (array $line): array => array_combine($header, $line), $lines);
}

it('builds the share of each locality’s residents in each district from ABS mesh blocks', function () {
    $folder = localityDataFolder();

    $this->artisan('vic:build-localities', ['--abs' => 'tests/Fixtures/Localities', '--data' => $folder])->assertSuccessful();

    expect(builtLocalities($folder))->toBe([
        ['sal_code' => '20003', 'locality' => 'Emptyvale', 'postcodes' => '3004', 'district' => 'Alpha', 'share' => '0.7500'],
        ['sal_code' => '20003', 'locality' => 'Emptyvale', 'postcodes' => '3004', 'district' => 'Beta', 'share' => '0.2500'],
        ['sal_code' => '20002', 'locality' => 'Splitton', 'postcodes' => '3003', 'district' => 'Alpha', 'share' => '0.9950'],
        ['sal_code' => '20001', 'locality' => 'Townsville', 'postcodes' => '3001 3002', 'district' => 'Alpha', 'share' => '0.8000'],
        ['sal_code' => '20001', 'locality' => 'Townsville', 'postcodes' => '3001 3002', 'district' => 'Beta', 'share' => '0.2000'],
    ]);
})->note('Emptyvale has no residents, so land area decides; Splitton’s 0.5% in Beta is a boundary sliver; other states and "No usual address" are left out.');

it('refuses to build localities when the ABS districts don’t match electorates.csv', function () {
    $folder = localityDataFolder(implode("\n", [
        'house,kind,name,slug,region',
        'council,region,Region One,region-one,',
        'council,region,Region Two,region-two,',
        'assembly,district,Alpha,alpha,Region One',
        'assembly,district,Beta,beta,Region Two',
    ]));

    $this->artisan('vic:build-localities', ['--abs' => 'tests/Fixtures/Localities', '--data' => $folder])
        ->expectsOutputToContain('These ABS districts do not match electorates.csv: Beta (Region One).')
        ->assertFailed();

    expect(File::exists("{$folder}/localities.csv"))->toBeFalse();
});

it('removes the ABS state suffix from names but keeps qualifiers that tell Victorian places apart', function (string $name, string $clean) {
    expect(LocalityBuilder::cleanName($name))->toBe($clean);
})->with([
    ['Abbotsford (Vic.)', 'Abbotsford'],
    ['Hillside (Melton - Vic.)', 'Hillside (Melton)'],
    ['Ascot (Ballarat)', 'Ascot (Ballarat)'],
]);

it('loads every locality, with its postcodes and district shares, into the database', function () {
    $this->artisan('vic:import-data')->assertSuccessful();

    $melbourne = Locality::query()->where('name', 'Melbourne')->with('electorates')->sole();

    expect(Locality::query()->count())->toBeGreaterThan(2900)
        ->and($melbourne->postcodes)->toContain('3000')
        ->and($melbourne->electorates->pluck('name'))->toContain('Melbourne')
        ->and(Electorate::query()->where('kind', 'district')->whereDoesntHave('localities')->count())->toBe(0);
});

it('serves the finder’s list of localities under the hash of its content, cached for a year', function () {
    $district = Electorate::factory()->create(['slug' => 'alpha', 'name' => 'Alpha']);
    Locality::factory()->create(['name' => 'Townsville', 'postcodes' => ['3001']])->electorates()->attach($district, ['share' => 1]);

    $response = $this->get(app(LocalityDirectory::class)->url());

    $response->assertOk()
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public')
        ->assertExactJson([
            'districts' => ['alpha' => 'Alpha'],
            'localities' => [['name' => 'Townsville', 'postcodes' => ['3001'], 'districts' => [['slug' => 'alpha', 'share' => 1.0]]]],
        ]);
});

it('sends a page loaded before the localities changed on to the current list', function () {
    $this->get(route('localities.show', str_repeat('0', 64)))
        ->assertRedirect(app(LocalityDirectory::class)->url())
        ->assertHeader('Cache-Control', 'no-cache, private');
});

it('republishes the quiz data after the reference data is imported, so results show the current MPs', function () {
    Policy::factory()->published()->create();

    $this->artisan('vic:import-data')->assertSuccessful();

    expect(app(StanceSnapshots::class)->current()?->payload)->toContain('"slug":"nina-taylor"');
});

it('rebuilds the finder’s list after the reference data is imported again', function () {
    $before = app(LocalityDirectory::class)->url();

    $this->artisan('vic:import-data')->assertSuccessful();

    expect(app(LocalityDirectory::class)->url())->not->toBe($before);
});
