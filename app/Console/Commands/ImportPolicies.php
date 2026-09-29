<?php

namespace App\Console\Commands;

use App\Domain\Policies\Importing\InvalidPolicyWorkbook;
use App\Domain\Policies\Importing\PolicyWorkbookImporter;
use App\Domain\Policies\Importing\PolicyWorkbookReader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

#[Signature('vic:import-policies {path=policy-research/policy-workbook-v2.xlsx : Path to the workbook on the default storage disk}')]
#[Description('Import policies and their linked divisions from the curation workbook, then recalculate scores')]
class ImportPolicies extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PolicyWorkbookReader $reader, PolicyWorkbookImporter $importer): int
    {
        $path = (string) $this->argument('path');

        if (! Storage::exists($path)) {
            $this->error("No workbook at [{$path}] on the default disk.");

            return self::FAILURE;
        }

        $bytes = (string) Storage::get($path);
        $localCopy = tempnam(sys_get_temp_dir(), 'policy-workbook-');
        file_put_contents($localCopy, $bytes);
        $this->line("Reading {$path} (sha256 ".hash('sha256', $bytes).')');

        try {
            $result = $importer->import($reader->read($localCopy));
        } catch (InvalidPolicyWorkbook $exception) {
            $this->error('The workbook was not imported. Fix these problems and try again:');

            foreach ($exception->errors as $error) {
                $this->line("  {$error}");
            }

            return self::FAILURE;
        } catch (RuntimeException $exception) {
            $this->error("The workbook could not be read: {$exception->getMessage()}");

            return self::FAILURE;
        } finally {
            unlink($localCopy);
        }

        $this->table(
            ['Status', 'Policies'],
            collect($result->policiesByStatus)->map(fn (int $count, string $status): array => [$status, $count])->values()->all(),
        );
        $this->info("{$result->links} linked divisions imported; {$result->removed} policies no longer in the workbook were removed.");

        return $this->call('vic:score');
    }
}
