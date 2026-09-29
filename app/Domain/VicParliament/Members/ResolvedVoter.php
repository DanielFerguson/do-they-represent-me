<?php

namespace App\Domain\VicParliament\Members;

final readonly class ResolvedVoter
{
    public function __construct(
        public int $memberId,
        public int $partyId,
        public string $printedName,
    ) {}
}
