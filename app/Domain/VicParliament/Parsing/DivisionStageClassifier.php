<?php

namespace App\Domain\VicParliament\Parsing;

use App\Enums\DivisionStage;

/**
 * Classifies what a division decided from the question put and the business
 * item it belongs to.
 */
class DivisionStageClassifier
{
    private const PROCEDURAL = '/be now put|closure|suspen|adjourn|postpone|business program|sessional order|standing order|leave be|be not now|debate be|time be extended|precedence|sitting of the|order of the day|stand part of|be omitted|be read and/i';

    private const PROCEDURAL_ITEM = '/^(ADJOURNMENT|GOVERNMENT BUSINESS PROGRAM|BUSINESS (POSTPONED|OF THE (HOUSE|COUNCIL))|SESSIONAL ORDERS|STANDING ORDERS|SUSPENSION OF|POSTPONEMENT OF|COMMITTEE MEMBERSHIP|PETITIONS|PAPERS|QUESTION TIME|SITTING OF|MEMBERS’ STATEMENTS|CONCURRENT DEBATE)/iu';

    public function classify(ParsedDivision $division): DivisionStage
    {
        $question = mb_strtolower($division->question ?? '');
        $item = $division->itemTitle ?? '';
        $isBill = preg_match('/\bbill\b/i', $item) === 1;

        return match (true) {
            str_contains($question, 'read a third time'), str_contains($question, 'and a third time') => DivisionStage::ThirdReading,
            str_contains($question, 'reasoned amendment') => DivisionStage::ReasonedAmendment,
            str_contains($question, 'read a second time') && ! str_contains($question, 'amendment') => DivisionStage::SecondReading,
            $isBill && (str_contains($question, 'introduce') || str_contains($question, 'read a first time')) => DivisionStage::BillIntroduction,
            $division->body === 'Committee' || preg_match('/\bclause|amendment/', $question) === 1 => DivisionStage::Amendment,
            preg_match(self::PROCEDURAL, $question) === 1, preg_match(self::PROCEDURAL_ITEM, $item) === 1 => DivisionStage::Procedural,
            $isBill => DivisionStage::Other,
            default => DivisionStage::Motion,
        };
    }
}
