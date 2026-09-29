<?php

namespace App\Domain\VicParliament\Parsing;

use Carbon\CarbonImmutable;

/**
 * A single division as recorded in the Votes and Proceedings (Assembly) or
 * Minutes of the Proceedings (Council).
 */
final readonly class ParsedDivision
{
    /**
     * @param  list<string>  $ayes  Member names exactly as printed ("First Last").
     * @param  list<string>  $noes  Member names exactly as printed ("First Last").
     * @param  list<string>  $tellers  Tellers, where recorded (usually conscience votes). Tellers are also included in $ayes or $noes.
     */
    public function __construct(
        public int $sittingNumber,
        public CarbonImmutable $sittingDate,
        public int $sequence,
        public string $body,
        public ?string $presidingRole,
        public ?string $presidingOfficer,
        public ?int $itemNumber,
        public ?string $itemTitle,
        public ?string $question,
        public int $ayesCount,
        public int $noesCount,
        public array $ayes,
        public array $noes,
        public ?string $result,
        public array $tellers = [],
    ) {}

    /**
     * The same division, held on a different date: for the rare cases where
     * the proceedings document is dated differently from the vote.
     */
    public function withSittingDate(CarbonImmutable $date): self
    {
        return new self(
            $this->sittingNumber, $date, $this->sequence, $this->body, $this->presidingRole, $this->presidingOfficer,
            $this->itemNumber, $this->itemTitle, $this->question, $this->ayesCount, $this->noesCount,
            $this->ayes, $this->noes, $this->result, $this->tellers,
        );
    }

    /**
     * Whether the printed names agree with the printed totals.
     */
    public function isConsistent(): bool
    {
        return count($this->ayes) === $this->ayesCount
            && count($this->noes) === $this->noesCount;
    }
}
