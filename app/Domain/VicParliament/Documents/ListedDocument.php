<?php

namespace App\Domain\VicParliament\Documents;

use Carbon\CarbonImmutable;

/**
 * A Votes and Proceedings or Minutes of the Proceedings document as listed
 * by the Parliament's house papers search.
 */
final readonly class ListedDocument
{
    public function __construct(
        public string $sourceKey,
        public string $title,
        public ?CarbonImmutable $startsOn,
        public ?CarbonImmutable $endsOn,
        public ?string $docxUrl,
        public ?string $pdfUrl,
        public ?int $firstSittingNumber,
        public ?int $lastSittingNumber,
    ) {}
}
