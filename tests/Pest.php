<?php

use App\Models\Division;
use App\Models\House;
use App\Models\Parliament;
use App\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * The records the fixture workbook refers to: the party its display note names, and the
 * divisions it links to, with the totals its Divisions tab lists.
 *
 * @return array<string, Division>
 */
function fixtureWorkbookDivisions(): array
{
    Party::factory()->create(['name' => 'Test Party']);
    $parliament = Parliament::factory()->create(['number' => 60]);
    $assembly = House::factory()->create(['short_name' => 'LA']);
    $council = House::factory()->create(['short_name' => 'LC']);

    return [
        'LA-60-092-02' => Division::factory()->for($parliament)->for($assembly)->create(['sitting_number' => 92, 'sequence' => 2, 'ayes_count' => 29, 'noes_count' => 54]),
        'LA-60-092-03' => Division::factory()->for($parliament)->for($assembly)->create(['sitting_number' => 92, 'sequence' => 3, 'ayes_count' => 54, 'noes_count' => 29]),
        'LC-60-025-01' => Division::factory()->for($parliament)->for($council)->create(['sitting_number' => 25, 'sequence' => 1, 'ayes_count' => 20, 'noes_count' => 15]),
    ];
}
