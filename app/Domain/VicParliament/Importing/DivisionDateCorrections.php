<?php

namespace App\Domain\VicParliament\Importing;

use Carbon\CarbonImmutable;
use RuntimeException;
use SplFileObject;

/**
 * Curated corrections for divisions whose proceedings document is dated
 * differently from the vote, kept in database/data with the source for
 * each correction. Keyed by division reference, e.g. "LC-60-025-01".
 */
class DivisionDateCorrections
{
    /**
     * @var array<string, CarbonImmutable>|null
     */
    private ?array $dates = null;

    public function __construct(private ?string $path = null) {}

    public function dateFor(string $reference): ?CarbonImmutable
    {
        $this->dates ??= $this->load();

        return $this->dates[$reference] ?? null;
    }

    /**
     * @return array<string, CarbonImmutable>
     */
    private function load(): array
    {
        $path = $this->path ?? database_path('data/division_date_corrections.csv');

        if (! is_file($path)) {
            return [];
        }

        $csv = new SplFileObject($path);
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
        $header = null;
        $dates = [];

        foreach ($csv as $index => $fields) {
            if (! is_array($fields) || $fields === [null]) {
                continue;
            }

            if ($header === null) {
                $header = $fields;

                continue;
            }

            $row = array_combine($header, array_map(fn (?string $value): string => trim((string) $value), $fields));

            if ($row['source'] === '') {
                throw new RuntimeException('division_date_corrections.csv line '.($index + 1).': every correction needs a source.');
            }

            $dates[$row['division']] = CarbonImmutable::parse($row['date']);
        }

        return $dates;
    }
}
