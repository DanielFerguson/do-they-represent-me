<?php

namespace App\Console\Commands;

use App\Domain\VicParliament\ReferenceData\ReferenceDataImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vic:import-data {--path= : Directory containing the reference CSV files (defaults to database/data)}')]
#[Description('Import curated reference data: houses, parliaments, parties, electorates, members, memberships and aliases')]
class ImportReferenceData extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ReferenceDataImporter $importer): int
    {
        $counts = $importer->import($this->option('path') ?: database_path('data'));

        $this->table(['File', 'Rows'], collect($counts)->map(fn (int $rows, string $file): array => [$file, $rows])->values()->all());

        return self::SUCCESS;
    }
}
