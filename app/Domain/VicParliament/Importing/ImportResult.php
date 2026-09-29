<?php

namespace App\Domain\VicParliament\Importing;

final readonly class ImportResult
{
    public const IMPORTED = 'imported';

    public const UNCHANGED = 'unchanged';

    public const FAILED = 'failed';

    public function __construct(
        public string $status,
        public int $divisions = 0,
        public int $needingReview = 0,
        public int $unresolvedNames = 0,
        public ?string $error = null,
    ) {}
}
