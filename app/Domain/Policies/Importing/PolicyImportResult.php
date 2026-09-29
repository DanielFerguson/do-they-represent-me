<?php

namespace App\Domain\Policies\Importing;

final readonly class PolicyImportResult
{
    /**
     * @param  array<string, int>  $policiesByStatus
     */
    public function __construct(
        public array $policiesByStatus,
        public int $links,
        public int $removed,
    ) {}
}
