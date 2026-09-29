<?php

namespace App\Console\Commands;

use App\Domain\Scoring\ScoreRecalculator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vic:score')]
#[Description('Recalculate party positions, policy agreement scores and the published quiz data')]
class CalculateScores extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ScoreRecalculator $recalculator): int
    {
        $result = $recalculator->recalculate();

        $this->info("Recalculated {$result['positions']} party positions and {$result['agreements']} policy agreement scores.");
        $this->line($result['snapshot'] === null
            ? 'No policies are published yet, so the quiz still shows the prototype.'
            : "Published quiz data version {$result['snapshot']->hash}.");

        return self::SUCCESS;
    }
}
