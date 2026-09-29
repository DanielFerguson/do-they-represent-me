<?php

namespace App\Jobs;

use App\Domain\VicParliament\Importing\ImportResult;
use App\Domain\VicParliament\Importing\ProceedingsDocumentImporter;
use App\Models\ProceedingsDocument;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Import the divisions from one proceedings document. Each document takes a
 * few seconds, well inside Laravel Cloud's queue time limit.
 */
class ImportProceedingsDocument implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 80;

    public function __construct(
        public ProceedingsDocument $document,
        public bool $force = false,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->document->id;
    }

    public function handle(ProceedingsDocumentImporter $importer): ImportResult
    {
        return $importer->import($this->document, $this->force);
    }
}
