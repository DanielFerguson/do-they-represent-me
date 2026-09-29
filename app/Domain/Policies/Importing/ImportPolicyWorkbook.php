<?php

namespace App\Domain\Policies\Importing;

use App\Domain\Scoring\ScoreRecalculator;
use App\Models\PolicyImport;
use App\Models\StanceSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Imports an uploaded or stored policy workbook, keeps a copy named by its
 * SHA-256, records who imported it, and recalculates scores. Used by the
 * vic:import-policies command and the admin panel's upload page.
 */
class ImportPolicyWorkbook
{
    public function __construct(
        private PolicyWorkbookReader $reader,
        private PolicyWorkbookImporter $importer,
        private ScoreRecalculator $recalculator,
    ) {}

    /**
     * @return array{import: PolicyImport, scores: array{positions: int, agreements: int, snapshot: ?StanceSnapshot}}
     *
     * @throws InvalidPolicyWorkbook when the workbook has problems; nothing is changed
     * @throws RuntimeException when the file cannot be read as a workbook
     */
    public function fromBytes(string $bytes, ?User $user = null): array
    {
        return Cache::lock('policy-workbook-import', 120)->block(10, function () use ($bytes, $user): array {
            $sha256 = hash('sha256', $bytes);
            $workbook = $this->read($bytes);

            return DB::transaction(function () use ($workbook, $bytes, $sha256, $user): array {
                $result = $this->importer->import($workbook);
                $path = "policy-research/workbooks/{$sha256}.xlsx";
                Storage::put($path, $bytes);

                $import = PolicyImport::query()->create([
                    'sha256' => $sha256,
                    'path' => $path,
                    'user_id' => $user?->id,
                    'summary' => ['policies_by_status' => $result->policiesByStatus, 'links' => $result->links, 'removed' => $result->removed],
                ]);

                return ['import' => $import, 'scores' => $this->recalculator->recalculate()];
            });
        });
    }

    private function read(string $bytes): PolicyWorkbook
    {
        $localCopy = tempnam(sys_get_temp_dir(), 'policy-workbook-');
        file_put_contents($localCopy, $bytes);

        try {
            return $this->reader->read($localCopy);
        } finally {
            unlink($localCopy);
        }
    }
}
