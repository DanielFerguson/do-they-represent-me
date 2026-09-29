<?php

use App\Domain\Policies\Importing\XlsxReader;

function workbookFixture(string $name): string
{
    return __DIR__.'/../Fixtures/Workbooks/'.$name;
}

it('reads shared, rich, inline, numeric, formula and boolean cells by row and column', function () {
    $rows = (new XlsxReader)->rows(workbookFixture('cell-types.xlsx'), 'Cell types');

    expect($rows)->toBe([
        1 => [0 => 'Heading', 1 => 'Bold and plain', 2 => 'With phonetic'],
        2 => [0 => 'Inline rich', 1 => '42', 2 => 'Inline rich!', 4 => '1', 5 => "Line one\nLine two"],
        4 => [0 => 'No reference', 1 => 'Next column'],
        6 => [27 => '7'],
    ]);
});

it('finds a sheet by name through the workbook relationships', function () {
    expect((new XlsxReader)->rows(workbookFixture('cell-types.xlsx'), 'Notes'))->toBe([1 => [0 => 'Heading']]);
});

it('rejects a sheet name the workbook does not have', function () {
    (new XlsxReader)->rows(workbookFixture('cell-types.xlsx'), 'Missing');
})->throws(RuntimeException::class, 'has no sheet named [Missing]');

it('rejects a file that is not an xlsx archive', function () {
    (new XlsxReader)->rows(__FILE__, 'Notes');
})->throws(RuntimeException::class, 'is not a valid .xlsx file');

it('rejects a workbook larger than the size limit', function () {
    (new XlsxReader(maxFileBytes: 100))->rows(workbookFixture('cell-types.xlsx'), 'Notes');
})->throws(RuntimeException::class, 'exceeds the size limit');
