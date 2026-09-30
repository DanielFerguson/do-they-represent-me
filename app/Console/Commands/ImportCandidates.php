<?php

namespace App\Console\Commands;

use App\Domain\Candidates\CandidateListImporter;
use App\Domain\Candidates\InvalidCandidateList;
use App\Models\Election;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('vic:import-candidates
    {election : The election slug, e.g. 2026}
    {--data=database/data : Folder holding party_ballot_names.csv and candidates/{election}.csv}')]
#[Description('Import an election\'s candidates, in ballot paper order, from database/data/candidates/{election}.csv')]
class ImportCandidates extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CandidateListImporter $importer): int
    {
        $slug = (string) $this->argument('election');
        $election = Election::query()->where('slug', $slug)->first();

        if ($election === null) {
            $this->error("No election [{$slug}]. Add it to database/data/elections.csv and run vic:import-data.");

            return self::FAILURE;
        }

        $data = rtrim((string) $this->option('data'), '/');
        $data = str_starts_with($data, '/') ? $data : base_path($data);

        try {
            $result = $importer->import($election, "{$data}/candidates/{$slug}.csv", "{$data}/party_ballot_names.csv");
        } catch (InvalidCandidateList $exception) {
            $this->error('The candidates were not imported. Fix these problems and try again:');

            foreach ($exception->errors as $error) {
                $this->line("  {$error}");
            }

            return self::FAILURE;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported {$result['candidates']} candidates for the {$election->name}.");
        $this->line('Check these candidates were matched to the right MP (put "-" in the member column to undo a match):');
        $this->table(['Electorate', 'Candidate', 'MP'], $result['matched']);

        return self::SUCCESS;
    }
}
