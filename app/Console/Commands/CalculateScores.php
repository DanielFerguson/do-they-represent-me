<?php

namespace App\Console\Commands;

use App\Domain\Scoring\PartyPositionCalculator;
use App\Domain\Scoring\PolicyAgreementCalculator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('vic:score')]
#[Description('Recalculate party positions for every division, then agreement scores for every policy')]
class CalculateScores extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PartyPositionCalculator $positions, PolicyAgreementCalculator $agreements): int
    {
        [$positionRows, $agreementRows] = DB::transaction(fn (): array => [$positions->recalculate(), $agreements->recalculate()]);

        $this->info("Recalculated {$positionRows} party positions and {$agreementRows} policy agreement scores.");

        return self::SUCCESS;
    }
}
