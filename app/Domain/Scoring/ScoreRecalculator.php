<?php

namespace App\Domain\Scoring;

use App\Domain\Stances\StanceSnapshots;
use App\Models\StanceSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * Recalculates everything derived from the votes and the policy links, in
 * one transaction: party positions, agreement scores, then the published
 * quiz data.
 */
class ScoreRecalculator
{
    public function __construct(
        private PartyPositionCalculator $positions,
        private PolicyAgreementCalculator $agreements,
        private StanceSnapshots $snapshots,
    ) {}

    /**
     * @return array{positions: int, agreements: int, snapshot: ?StanceSnapshot}
     */
    public function recalculate(): array
    {
        return DB::transaction(fn (): array => [
            'positions' => $this->positions->recalculate(),
            'agreements' => $this->agreements->recalculate(),
            'snapshot' => $this->snapshots->publish(),
        ]);
    }
}
