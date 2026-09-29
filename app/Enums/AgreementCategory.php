<?php

namespace App\Enums;

/**
 * How consistently a party or member voted the way a policy's "agree"
 * answer would, using TheyVoteForYou's bands.
 */
enum AgreementCategory: string
{
    case For3 = 'for3';
    case For2 = 'for2';
    case For1 = 'for1';
    case Mixture = 'mixture';
    case Against1 = 'against1';
    case Against2 = 'against2';
    case Against3 = 'against3';
    case NotEnough = 'not_enough';
    case DidNotVote = 'did_not_vote';

    public static function forAgreement(float $agreement): self
    {
        $agreement = round($agreement, 4);

        return match (true) {
            $agreement >= 0.95 => self::For3,
            $agreement >= 0.85 => self::For2,
            $agreement >= 0.60 => self::For1,
            $agreement >= 0.40 => self::Mixture,
            $agreement >= 0.15 => self::Against1,
            $agreement >= 0.05 => self::Against2,
            default => self::Against3,
        };
    }

    /**
     * Whether the record supports a stated position (and so a match figure).
     */
    public function isScored(): bool
    {
        return ! in_array($this, [self::NotEnough, self::DidNotVote], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::For3 => 'Consistently for',
            self::For2 => 'Almost always for',
            self::For1 => 'Generally for',
            self::Mixture => 'Mixed',
            self::Against1 => 'Generally against',
            self::Against2 => 'Almost always against',
            self::Against3 => 'Consistently against',
            self::NotEnough => 'Not enough votes',
            self::DidNotVote => 'Did not vote',
        };
    }
}
