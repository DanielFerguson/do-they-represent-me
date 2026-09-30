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

it('attributes council committee of the whole divisions to the bill in the supplement', function () {
    $division = collect(parseFixture('council-2025-m128-130.docx'))
        ->first(fn (ParsedDivision $division) => $division->body === 'Committee');

    expect($division->itemTitle)->toBe('WORKER SCREENING AMENDMENT (STRENGTHENING THE WORKING WITH CHILDREN CHECK) BILL 2025')
        ->and($division->itemNumber)->toBeNull()
        ->and($division->question)->toBe('Question — That the amendments be agreed to — put.');
});

it('recognises a supplement bill heading printed with "bill" in lower case', function () {
    $divisions = (new ProceedingsParser)->parse([
        'No. 112 — Thursday, 3 April 2025',
        '13ADJOURNMENT — Ingrid Stitt moved, That the House do now adjourn.',
        'COMMITTEE OF THE WHOLE COUNCIL',
        'JUSTICE LEGISLATION AMENDMENT (ANTI-VILIFICATION AND SOCIAL COHESION) bill 2024',
        'Committed Tuesday, 1 April 2025',
        'Clause 9 — Evan Mulholland moved amendment No. 1 (EM25C).',
        'Question — That the amendment be agreed to — put.',
        'The Committee divided — The Deputy President in the Chair.',
        'AYES, 1',
        'Melina Bath.',
        'NOES, 2',
        'Ryan Batchelor; John Berger.',
        'Question negatived.',
    ]);

    expect($divisions)->toHaveCount(1)
        ->and($divisions[0]->itemTitle)->toBe('JUSTICE LEGISLATION AMENDMENT (ANTI-VILIFICATION AND SOCIAL COHESION) BILL 2024')
        ->and($divisions[0]->itemNumber)->toBeNull();
});

it('dates a supplement committee division to the day the bill was committed', function () {
    $divisions = (new ProceedingsParser)->parse([
        'No. 110 — Tuesday, 1 April 2025',
        'No. 112 — Thursday, 3 April 2025',
        'COMMITTEE OF THE WHOLE COUNCIL',
        'JUSTICE LEGISLATION AMENDMENT (ANTI-VILIFICATION AND SOCIAL COHESION) BILL 2024',
        'Committed Tuesday, 1 April 2025',
        'Question — That the amendment be agreed to — put.',
        'The Committee divided — The Deputy President in the Chair.',
        'AYES, 1',
        'Melina Bath.',
        'NOES, 2',
        'Ryan Batchelor; John Berger.',
        'Question negatived.',
        'SOCIAL SERVICES REGULATION AMENDMENT BILL 2025',
        'Question — That the amendment be agreed to — put.',
        'The Committee divided — The Deputy President in the Chair.',
        'AYES, 1',
        'Melina Bath.',
        'NOES, 2',
        'Ryan Batchelor; John Berger.',
        'Question negatived.',
    ]);

    expect($divisions)->toHaveCount(2)
        ->and($divisions[0]->sittingNumber)->toBe(112)
        ->and($divisions[0]->sittingDate->toDateString())->toBe('2025-04-01')
        ->and($divisions[1]->sittingDate->toDateString())->toBe('2025-04-03');
});

it('looks past a bare "Question — put." to the motion that was put', function () {
    $divisions = (new ProceedingsParser)->parse([
        'No. 137 — Tuesday, 28 October 2025',
        '9 STATEWIDE TREATY BILL 2025 — Debate resumed on the question, That the Bill be now read a second time.',
        'Debate continued.',
        'Question — put.',
        'The Council divided — The President in the Chair.',
        'AYES, 2',
        'Ryan Batchelor; John Berger.',
        'NOES, 1',
        'Melina Bath.',
        'Question agreed to.',
        'Bill read a second time.',
        'Lizzie Blandthorn moved, That the Bill be now read a third time and do pass.',
        'Question — put.',
        'The Council divided — The President in the Chair.',
        'AYES, 2',
        'Ryan Batchelor; John Berger.',
        'NOES, 1',
        'Melina Bath.',
        'Question agreed to.',
    ]);

    expect($divisions)->toHaveCount(2)
        ->and($divisions[0]->question)->toBe('9 STATEWIDE TREATY BILL 2025 — Debate resumed on the question, That the Bill be now read a second time.')
        ->and($divisions[0]->itemTitle)->toBe('STATEWIDE TREATY BILL 2025')
        ->and($divisions[1]->question)->toBe('Lizzie Blandthorn moved, That the Bill be now read a third time and do pass.')
        ->and($divisions[1]->itemTitle)->toBe('STATEWIDE TREATY BILL 2025');
});

it('recognises a business item heading with no space before the dash', function () {
    $divisions = (new ProceedingsParser)->parse([
        'No. 30 — Wednesday, 16 August 2023',
        '6 Nuclear activities (prohibitions) repeal bill 2023 — David Limbrick moved, That the Bill be now read a second time.',
        '7 Independent Broad-based Anti-corruption Commission Amendment (Facilitation of Timely Reporting) Bill 2022— Debate resumed on the question, That the Bill be now read a second time.',
        'David Davis moved, That the Bill be now read a third time and do pass.',
        'Question — put.',
        'The Council divided — The President in the Chair.',
        'AYES, 1',
        'David Davis.',
        'NOES, 1',
        'Jaclyn Symes.',
        'Question agreed to.',
    ]);

    expect($divisions[0]->itemNumber)->toBe(7)
        ->and($divisions[0]->itemTitle)->toBe('Independent Broad-based Anti-corruption Commission Amendment (Facilitation of Timely Reporting) Bill 2022');
});
