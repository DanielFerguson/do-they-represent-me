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

    public function asVote(): ?VoteValue
    {
        return match ($this) {
            self::Aye => VoteValue::Aye,
            self::No => VoteValue::No,
            default => null,
        };
    }
}
