<?php

namespace App\Enums;

/**
 * The kind of question a division decided.
 */
enum DivisionStage: string
{
    case BillIntroduction = 'bill_introduction';
    case SecondReading = 'second_reading';
    case ThirdReading = 'third_reading';
    case ReasonedAmendment = 'reasoned_amendment';
    case Amendment = 'amendment';
    case Motion = 'motion';
    case Procedural = 'procedural';
    case Other = 'other';

    /**
     * Second and third readings decide whether a bill passes, so they are
     * the "strong" votes by default when linked to a policy.
     */
    public function isDecisive(): bool
    {
        return in_array($this, [self::SecondReading, self::ThirdReading], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::BillIntroduction => 'Bill introduction',
            self::SecondReading => 'Second reading',
            self::ThirdReading => 'Third reading',
            self::ReasonedAmendment => 'Reasoned amendment',
            self::Amendment => 'Amendment',
            self::Motion => 'Motion',
            self::Procedural => 'Procedural',
            self::Other => 'Other',
        };
    }
}
