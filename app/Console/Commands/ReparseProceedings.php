<?php

namespace App\Console\Commands;

use App\Domain\VicParliament\Importing\ImportResult;
use App\Domain\VicParliament\Importing\ProceedingsDocumentImporter;
use App\Models\ProceedingsDocument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vic:reparse {--house=* : Limit to "assembly" or "council" (default: both)}')]
#[Description('Re-import divisions from the stored copies of proceedings documents, without downloading them again')]
class ReparseProceedings extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ProceedingsDocumentImporter $importer): int
    {
        $documents = ProceedingsDocument::query()
            ->whereNotNull('raw_path')
            ->when($this->option('house'), fn ($query, array $slugs) => $query->whereHas('house', fn ($query) => $query->whereIn('slug', $slugs)))
            ->orderBy('starts_on')
            ->get();

        $totals = ['imported' => 0, 'failed' => 0, 'divisions' => 0, 'review' => 0];

        $this->withProgressBar($documents, function (ProceedingsDocument $document) use ($importer, &$totals): void {
            $result = $importer->reimportFromStorage($document);
            $totals[$result->status === ImportResult::IMPORTED ? 'imported' : 'failed']++;
            $totals['divisions'] += $result->divisions;
            $totals['review'] += $result->needingReview;
        });

        $this->newLine(2);
        $this->table(array_keys($totals), [array_values($totals)]);

        return $totals['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
