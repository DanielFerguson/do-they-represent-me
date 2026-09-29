<?php

use App\Domain\VicParliament\Importing\DivisionDateCorrections;

function corrections(string $fixture): DivisionDateCorrections
{
    return new DivisionDateCorrections(__DIR__.'/../Fixtures/DivisionDateCorrections/'.$fixture);
}

it('gives the corrected date for a listed division and none for others', function () {
    $corrections = corrections('one-correction.csv');

    expect($corrections->dateFor('LC-60-128-01')?->toDateString())->toBe('2025-08-25')
        ->and($corrections->dateFor('LC-60-128-02'))->toBeNull();
});

it('rejects a correction without a source', function () {
    corrections('missing-source.csv')->dateFor('LC-60-128-01');
})->throws(RuntimeException::class, 'division_date_corrections.csv line 2: every correction needs a source.');
