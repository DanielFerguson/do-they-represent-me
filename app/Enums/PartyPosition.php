<?php

namespace App\Enums;

/**
 * How a party voted in a division, by strict majority of its members present.
 */
enum PartyPosition: string
{
    case Aye = 'aye';
    case No = 'no';
    case Split = 'split';
    case None = 'none';

    /**
     * A strict majority of the members who voted. A tie is a split, and a
     * party with no members voting has no position.
     */
    public static function fromCounts(int $ayes, int $noes): self
    {
        return match (true) {
            $ayes > $noes => self::Aye,
            $noes > $ayes => self::No,
            $ayes > 0 => self::Split,
            default => self::None,
        };
    }

    public function asVote(): ?VoteValue
    {
        return match ($this) {
            self::Aye => VoteValue::Aye,
            self::No => VoteValue::No,
            default => null,
        };
    }
}
