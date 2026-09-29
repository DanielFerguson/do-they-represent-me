<?php

use App\Domain\VicParliament\Parsing\DivisionStageClassifier;
use App\Domain\VicParliament\Parsing\ParsedDivision;
use App\Enums\DivisionStage;
use Carbon\CarbonImmutable;

function divisionWith(?string $question, ?string $item = 'EXAMPLE BILL 2025', string $body = 'House'): ParsedDivision
{
    return new ParsedDivision(
        sittingNumber: 1, sittingDate: CarbonImmutable::parse('2025-01-01'), sequence: 1, body: $body,
        presidingRole: null, presidingOfficer: null, itemNumber: 1, itemTitle: $item, question: $question,
        ayesCount: 0, noesCount: 0, ayes: [], noes: [], result: null,
    );
}

it('classifies divisions by what they decided', function (?string $question, ?string $item, string $body, DivisionStage $expected) {
    expect((new DivisionStageClassifier)->classify(divisionWith($question, $item, $body)))->toBe($expected);
})->with([
    'second reading' => ['Question — That this Bill be now read a second time — put.', 'EXAMPLE BILL 2025', 'House', DivisionStage::SecondReading],
    'second and third reading together' => ['Question — That this Bill be now read a second time and a third time — put.', 'EXAMPLE BILL 2025', 'House', DivisionStage::ThirdReading],
    'council third reading' => ['Jaclyn Symes moved, That the Bill be now read a third time and do pass.', 'Example Bill 2025', 'Council', DivisionStage::ThirdReading],
    'reasoned amendment' => ['Question — That the reasoned amendment moved by Georgie Crozier be agreed to — put.', 'EXAMPLE BILL 2025', 'Council', DivisionStage::ReasonedAmendment],
    'second reading amendment' => ['Question — That the words proposed to be omitted stand part of the question — put.', 'EXAMPLE BILL 2025', 'House', DivisionStage::Procedural],
    'committee amendment' => ['Question — That the amendments be agreed to — put.', 'EXAMPLE BILL 2025', 'Committee', DivisionStage::Amendment],
    'bill introduction' => ['Motion made and question — That the Member for Caulfield introduce ‘A Bill for an Act…’', 'SAFER PROTEST BILL 2025', 'House', DivisionStage::BillIntroduction],
    'substantive motion' => ['Question — That the motion be agreed to — put.', 'NUCLEAR POWER', 'House', DivisionStage::Motion],
    'business program' => ['Question — That the motion be agreed to — put.', 'GOVERNMENT BUSINESS PROGRAM', 'House', DivisionStage::Procedural],
    'unknown question on a bill' => [null, 'EXAMPLE BILL 2025', 'Council', DivisionStage::Other],
]);
