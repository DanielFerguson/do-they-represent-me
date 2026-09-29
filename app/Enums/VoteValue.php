<?php

namespace App\Enums;

enum VoteValue: string
{
    case Aye = 'aye';
    case No = 'no';

    public function opposite(): self
    {
        return $this === self::Aye ? self::No : self::Aye;
    }
}
