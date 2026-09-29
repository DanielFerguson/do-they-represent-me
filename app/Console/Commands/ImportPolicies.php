<?php

namespace App\Console\Commands;

use App\Domain\Policies\Importing\ImportPolicyWorkbook;
use App\Domain\Policies\Importing\InvalidPolicyWorkbook;
use App\Models\PolicyImport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

#[Signature('vic:import-policies {path? : Path to the workbook on the default storage disk (default: the last workbook imported)}')]
#[Description('Import policies and their linked divisions from the curation workbook, then recalculate scores')]
class ImportPolicies extends Command
{
    /**
     * The workbook produced by the policy curation, used until another has been imported.
     */
    public const INITIAL_WORKBOOK = 'policy-research/policy-workbook-v2.xlsx';

    /**
     * Execute the console command.
     */
    public function handle(ImportPolicyWorkbook $importer): int
    {
        $path = $this->argument('path') ?? PolicyImport::query()->latest('id')->value('path') ?? self::INITIAL_WORKBOOK;

        if (! Storage::exists($path)) {
            $this->error("No workbook at [{$path}] on the default disk.");

            return self::FAILURE;
        }

        $bytes = (string) Storage::get($path);
        $this->line("Reading {$path} (sha256 ".hash('sha256', $bytes).')');

        try {
            ['import' => $import, 'scores' => $scores] = $importer->fromBytes($bytes);
        } catch (InvalidPolicyWorkbook $exception) {
            $this->error('The workbook was not imported. Fix these problems and try again:');

            foreach ($exception->errors as $error) {
                $this->line("  {$error}");
            }

            return self::FAILURE;
        } catch (RuntimeException $exception) {
            $this->error("The workbook could not be read: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->table(
            ['Status', 'Policies'],
            collect($import->summary['policies_by_status'])->map(fn (int $count, string $status): array => [$status, $count])->values()->all(),
        );
        $this->info("{$import->summary['links']} linked divisions imported; {$import->summary['removed']} policies no longer in the workbook were removed.");
        $this->info("Recalculated {$scores['positions']} party positions and {$scores['agreements']} policy agreement scores.");

        return self::SUCCESS;
    }
}
