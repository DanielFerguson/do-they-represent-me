<?php

use App\Filament\Pages\PolicyWorkbook;
use App\Models\Policy;
use App\Models\PolicyImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    config(['admin.emails' => ['curator@example.com']]);
});

function uploadedWorkbook(string $fixture = 'policy-workbook.xlsx'): UploadedFile
{
    return UploadedFile::fake()->createWithContent('workbook.xlsx', (string) file_get_contents(base_path("tests/Fixtures/Workbooks/{$fixture}")));
}

it('imports an uploaded workbook, records who uploaded it and recalculates scores', function () {
    Storage::fake();
    fixtureWorkbookDivisions();
    $curator = User::factory()->withAppAuthentication()->create(['email' => 'curator@example.com']);
    $this->actingAs($curator);

    Livewire::test(PolicyWorkbook::class)
        ->fillForm(['workbook' => uploadedWorkbook()])
        ->call('import')
        ->assertHasNoFormErrors()
        ->assertNotified('Workbook imported')
        ->assertSet('problems', []);

    $import = PolicyImport::query()->sole();
    expect($import->user_id)->toBe($curator->id)
        ->and($import->sha256)->toBe(hash_file('sha256', base_path('tests/Fixtures/Workbooks/policy-workbook.xlsx')))
        ->and(Policy::query()->pluck('number')->sort()->values()->all())->toBe([1, 2, 3]);
    Storage::assertExists($import->path);
});

it('lists every problem and changes nothing when the workbook is invalid', function () {
    Storage::fake();
    fixtureWorkbookDivisions()['LA-60-092-03']->update(['noes_count' => 30]);
    $this->actingAs(User::factory()->withAppAuthentication()->create(['email' => 'curator@example.com']));

    Livewire::test(PolicyWorkbook::class)
        ->fillForm(['workbook' => uploadedWorkbook()])
        ->call('import')
        ->assertNotified('The workbook was not imported')
        ->assertSet('problems', ['Policy votes row 3: LA-60-092-03 is 54–29 on the Divisions tab but 54–30 in the imported proceedings. Check the division ID.'])
        ->assertSee('LA-60-092-03 is 54–29 on the Divisions tab');

    expect(Policy::query()->count())->toBe(0)
        ->and(PolicyImport::query()->count())->toBe(0);
});

it('shows the latest import', function () {
    $this->actingAs(User::factory()->withAppAuthentication()->create(['email' => 'curator@example.com']));
    $import = PolicyImport::factory()->create();

    Livewire::test(PolicyWorkbook::class)->assertSee($import->sha256)->assertSee('Command line');
});
