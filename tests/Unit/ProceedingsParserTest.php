<?php

use App\Domain\VicParliament\Documents\DocxReader;
use App\Domain\VicParliament\Parsing\ParsedDivision;
use App\Domain\VicParliament\Parsing\ProceedingsParser;

/**
 * @return list<ParsedDivision>
 */
function parseFixture(string $name): array
{
    $paragraphs = (new DocxReader)->paragraphs(__DIR__.'/../Fixtures/VotesAndProceedings/'.$name);

    return (new ProceedingsParser)->parse($paragraphs);
}

dataset('fixtures', [
    'assembly 2025' => ['assembly-2025-vp131-134.docx', 8],
    'assembly 2023' => ['assembly-2023-vp002-005.docx', 5],
    'council 2025' => ['council-2025-m128-130.docx', 25],
    'council 2023' => ['council-2023-m002-004.docx', 2],
    'assembly 2026 without divisions' => ['assembly-2026-vp202-no-divisions.docx', 0],
    'assembly 2025 conscience vote' => ['assembly-2025-vp147-149-conscience-vote-tellers.docx', 7],
    'council 2023 comma list' => ['council-2023-m020-022-comma-list.docx', 9],
    'council 2024 mixed separators' => ['council-2024-m053-055-mixed-separators.docx', 5],
]);

it('finds every division and the printed names match the printed totals', function (string $fixture, int $expectedDivisions) {
    $divisions = parseFixture($fixture);

    expect($divisions)->toHaveCount($expectedDivisions);

    foreach ($divisions as $division) {
        expect($division->isConsistent())->toBeTrue(
            "Division {$division->sittingNumber}/{$division->sequence} printed {$division->ayesCount}/{$division->noesCount} but parsed ".count($division->ayes).'/'.count($division->noes),
        );
    }
})->with('fixtures');

it('parses an assembly division with its sitting, chair, question and result', function () {
    $division = parseFixture('assembly-2025-vp131-134.docx')[0];

    expect($division->sequence)->toBe(1)
        ->and($division->sittingDate->toDateString())->toBe('2025-07-29');

    expect($division)
        ->body->toBe('House')
        ->presidingRole->toBe('Deputy Speaker')
        ->presidingOfficer->toBe('Matt Fregon')
        ->ayesCount->toBe(32)
        ->noesCount->toBe(49)
        ->result->toBe('Question defeated.')
        ->and($division->itemTitle)->toStartWith('WORKER SCREENING AMENDMENT (SAFETY OF CHILDREN) BILL 2025')
        ->and($division->ayes[0])->toBe('Brad Battin')
        ->and($division->noes)->toContain('Jacinta Allan');
});

it('parses a council division where the President is in the chair', function () {
    $division = parseFixture('council-2025-m128-130.docx')[0];

    expect($division->sittingDate->toDateString())->toBe('2025-08-26');

    expect($division)
        ->body->toBe('Council')
        ->presidingRole->toBe('President')
        ->presidingOfficer->toBeNull()
        ->ayesCount->toBe(7)
        ->noesCount->toBe(28)
        ->result->toBe('Question negatived.')
        ->question->toBe('Question — That the reasoned amendment moved by Katherine Copsey be agreed to — put.')
        ->and($division->itemTitle)->toBe('Bail Further Amendment Bill 2025')
        ->and($division->ayes)->toContain('Katherine Copsey')
        ->and($division->ayes)->not->toContain('(Recorded by Clerks-at-the-Table, pursuant to an order of the Council on 23 April 2020)');
});

it('strips trailing full stops and keeps names with apostrophes and hyphens', function () {
    $names = collect(parseFixture('assembly-2025-vp131-134.docx'))
        ->flatMap(fn (ParsedDivision $division) => [...$division->ayes, ...$division->noes]);

    expect($names)
        ->each->not->toEndWith('.')
        ->and($names)->toContain('Danny O’Brien', 'Michael O’Brien', 'Lily D’Ambrosio');
});

it('numbers divisions from one within each sitting', function () {
    $divisions = collect(parseFixture('council-2025-m128-130.docx'));

    $divisions->groupBy('sittingNumber')->each(function ($sitting) {
        expect($sitting->pluck('sequence')->all())->toBe(range(1, $sitting->count()));
    });

    expect($divisions->pluck('sittingNumber')->unique()->values()->all())->toBe([128, 129, 130]);
});

it('counts tellers as votes on their side in conscience votes', function () {
    $division = collect(parseFixture('assembly-2025-vp147-149-conscience-vote-tellers.docx'))
        ->first(fn (ParsedDivision $division) => $division->sittingNumber === 147 && $division->sequence === 2);

    expect($division->isConsistent())->toBeTrue()
        ->and($division->tellers)->toBe(['Bronwyn Halfpenny', 'Michaela Settle', 'Chris Crewther', 'Kim Wells'])
        ->and($division->ayes)->toContain('Jess Wilson', 'Bronwyn Halfpenny', 'Michaela Settle')
        ->and($division->noes)->toContain('Chris Crewther', 'Kim Wells');
});

it('keeps non-breaking hyphens in names', function () {
    $names = collect(parseFixture('assembly-2025-vp147-149-conscience-vote-tellers.docx'))
        ->flatMap(fn (ParsedDivision $division) => [...$division->ayes, ...$division->noes]);

    expect($names)->toContain('Mary-Anne Thomas', 'Kathleen Matthews-Ward')
        ->not->toContain('MaryAnne Thomas');
});

it('reads short comma separated lists', function () {
    $division = parseFixture('council-2023-m020-022-comma-list.docx')[0];

    expect($division->noes)->toBe(['David Limbrick', 'Georgie Purcell']);
});

it('splits a comma that slipped into a semicolon separated list', function () {
    $division = collect(parseFixture('council-2024-m053-055-mixed-separators.docx'))
        ->first(fn (ParsedDivision $division) => $division->sittingNumber === 54);

    expect($division->isConsistent())->toBeTrue()
        ->and($division->ayes)->toContain('Georgie Purcell', 'Samantha Ratnam');
});

it('only ever records personal names as voters', function (string $fixture) {
    $names = collect(parseFixture($fixture))
        ->flatMap(fn (ParsedDivision $division) => [...$division->ayes, ...$division->noes]);

    $notNames = $names->reject(fn (string $name) => preg_match("/^\\p{Lu}[\\p{L}’'\\-]+(?: [\\p{L}’'\\-]+){1,3}$/u", $name) === 1);

    expect($notNames->all())->toBe([]);
})->with('fixtures');
