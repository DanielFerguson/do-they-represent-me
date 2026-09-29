<?php

namespace App\Console\Commands;

use App\Domain\VicParliament\Documents\ListedDocument;
use App\Domain\VicParliament\Documents\ProceedingsListing;
use App\Domain\VicParliament\Importing\ImportResult;
use App\Domain\VicParliament\Importing\ProceedingsDocumentImporter;
use App\Jobs\ImportProceedingsDocument;
use App\Models\House;
use App\Models\Parliament;
use App\Models\ProceedingsDocument;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vic:sync-proceedings
    {--house=* : Limit to "assembly" or "council" (default: both)}
    {--since= : Only documents covering sittings on or after this date (default: start of the current parliament)}
    {--force : Re-import documents even if they have not changed}
    {--queue : Push imports onto the queue instead of running them now}')]
#[Description('Find new Votes and Proceedings / Minutes documents and import their divisions')]
class SyncProceedings extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ProceedingsListing $listing, ProceedingsDocumentImporter $importer): int
    {
        $since = $this->since();
        $houses = House::query()
            ->when($this->option('house'), fn ($query, array $slugs) => $query->whereIn('slug', $slugs))
            ->get();

        if ($houses->isEmpty()) {
            $this->error('No houses found. Run vic:import-data first.');

            return self::FAILURE;
        }

        $totals = ['imported' => 0, 'unchanged' => 0, 'failed' => 0, 'divisions' => 0, 'review' => 0];

        foreach ($houses as $house) {
            $documents = $listing->since($house, $since)
                ->filter(fn (ListedDocument $listed): bool => $listed->startsOn === null || $listed->startsOn->gte($since))
                ->map(fn (ListedDocument $listed): ProceedingsDocument => $this->record($house, $listed))
                ->sortBy('starts_on');

            $this->info("{$house->name}: {$documents->count()} documents since {$since->toDateString()}");

            foreach ($documents as $document) {
                if ($this->option('queue')) {
                    ImportProceedingsDocument::dispatch($document, (bool) $this->option('force'));

                    continue;
                }

                $result = $importer->import($document, (bool) $this->option('force'));
                $totals[$result->status]++;
                $totals['divisions'] += $result->status === ImportResult::IMPORTED ? $result->divisions : 0;
                $totals['review'] += $result->needingReview;

                $line = sprintf('  %-28s %-9s %3d divisions', $document->title, $result->status, $result->divisions);
                $result->status === ImportResult::FAILED ? $this->warn($line.'  '.$result->error) : $this->line($line);
            }
        }

        if ($this->option('queue')) {
            $this->info('Imports queued.');

            return self::SUCCESS;
        }

        $this->table(array_keys($totals), [array_values($totals)]);

        return $totals['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function since(): CarbonImmutable
    {
        if ($this->option('since')) {
            return CarbonImmutable::parse($this->option('since'))->startOfDay();
        }

        $parliament = Parliament::query()->orderByDesc('number')->firstOrFail();

        return CarbonImmutable::parse($parliament->starts_on)->startOfDay();
    }

    private function record(House $house, ListedDocument $listed): ProceedingsDocument
    {
        return ProceedingsDocument::query()->updateOrCreate(['source_key' => $listed->sourceKey], [
            'house_id' => $house->id,
            'title' => $listed->title,
            'first_sitting_number' => $listed->firstSittingNumber,
            'last_sitting_number' => $listed->lastSittingNumber,
            'starts_on' => $listed->startsOn,
            'ends_on' => $listed->endsOn,
            'docx_url' => $listed->docxUrl ?? '',
            'pdf_url' => $listed->pdfUrl,
        ]);
    }
}
