<?php

namespace App\Console\Commands;

use App\Domain\Localities\LocalityBuilder;
use App\Domain\Policies\Importing\XlsxReader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;
use SplFileObject;

#[Signature('vic:build-localities
    {--abs=storage/app/private/reference-data/abs : Folder holding the four ABS workbooks}
    {--ebc= : The EBC\'s "State Districts 2022 - Localities.xlsx", to cross-check the result}
    {--data=database/data : Folder holding electorates.csv, and where localities.csv is written}')]
#[Description('Build localities.csv (suburbs and postcodes to districts) from ABS mesh block data')]
class BuildLocalities extends Command
{
    public const SED_FILE = 'SED_2025_AUST.xlsx';

    public const SAL_FILE = 'SAL_2021_AUST.xlsx';

    public const POA_FILE = 'POA_2021_AUST.xlsx';

    public const COUNTS_FILE = 'Mesh Block Counts, 2021.xlsx';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        ini_set('memory_limit', '4G');

        $abs = $this->path('abs');
        $data = $this->path('data');
        $paths = [];

        foreach ([self::SED_FILE, self::SAL_FILE, self::POA_FILE, self::COUNTS_FILE] as $file) {
            $paths[$file] = "{$abs}/{$file}";

            if (! is_file($paths[$file])) {
                $this->error("Missing {$paths[$file]}. See docs/methodology/data-pipeline.md for where to download it.");

                return self::FAILURE;
            }

            $this->line("{$file}: sha256 ".hash_file('sha256', $paths[$file]));
        }

        $builder = new LocalityBuilder(new XlsxReader(maxFileBytes: 100 * 1024 * 1024, maxEntryBytes: 500 * 1024 * 1024));

        try {
            $rows = $builder->build(
                $paths[self::SED_FILE],
                $paths[self::SAL_FILE],
                $paths[self::POA_FILE],
                $paths[self::COUNTS_FILE],
                $this->districtRegions("{$data}/electorates.csv"),
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->write("{$data}/localities.csv", $rows);

        $localities = count(array_unique(array_column($rows, 'sal_code')));
        $split = $localities - count(array_filter(array_count_values(array_column($rows, 'sal_code')), fn (int $count): bool => $count === 1));
        $this->info("Wrote {$localities} localities ({$split} split between districts) to {$data}/localities.csv.");

        if ($this->option('ebc')) {
            $this->crossCheck($this->path('ebc'), $rows);
        }

        return self::SUCCESS;
    }

    /**
     * An option's path, relative to the project unless absolute.
     */
    private function path(string $option): string
    {
        $path = rtrim((string) $this->option($option), '/');

        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    /**
     * @return array<string, string>
     */
    private function districtRegions(string $path): array
    {
        $regions = [];
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
        $header = null;

        foreach ($file as $row) {
            if (! is_array($row) || $row === [null]) {
                continue;
            }

            if ($header === null) {
                $header = $row;

                continue;
            }

            $values = array_combine($header, $row);

            if ($values['kind'] === 'district') {
                $regions[$values['name']] = $values['region'];
            }
        }

        return $regions;
    }

    /**
     * @param  list<array{sal_code: string, locality: string, postcodes: list<string>, district: string, share: float}>  $rows
     */
    private function write(string $path, array $rows): void
    {
        $file = new SplFileObject($path, 'w');
        $file->fputcsv(['sal_code', 'locality', 'postcodes', 'district', 'share'], escape: '');

        foreach ($rows as $row) {
            $file->fputcsv([$row['sal_code'], $row['locality'], implode(' ', $row['postcodes']), $row['district'], number_format($row['share'], 4, '.', '')], escape: '');
        }
    }

    /**
     * Lists localities whose largest district isn't among those the EBC
     * lists them under. Names are compared in capitals without qualifiers.
     *
     * @param  list<array{sal_code: string, locality: string, postcodes: list<string>, district: string, share: float}>  $rows
     */
    private function crossCheck(string $path, array $rows): void
    {
        $listed = [];

        foreach ((new XlsxReader)->rows($path, 'Localities') as $cells) {
            [$district, $localities] = [$cells[0] ?? '', $cells[1] ?? ''];

            foreach (explode(',', $localities) as $locality) {
                $listed[self::comparable($locality)][strtoupper(trim($district))] = true;
            }
        }

        $largest = [];

        foreach ($rows as $row) {
            $largest[$row['sal_code']] ??= $row;
        }

        $differences = [];

        foreach ($largest as $row) {
            $districts = $listed[self::comparable($row['locality'])] ?? null;

            if ($districts === null) {
                $differences[] = [$row['locality'], $row['district'], 'not in the EBC list'];
            } elseif (! isset($districts[strtoupper($row['district'])])) {
                $differences[] = [$row['locality'], $row['district'], 'EBC: '.implode(', ', array_keys($districts))];
            }
        }

        $this->line(count($differences).' of '.count($largest).' localities differ from the EBC list:');
        $this->table(['Locality', 'Largest district here', 'EBC'], $differences);
    }

    private static function comparable(string $name): string
    {
        return strtoupper(trim((string) preg_replace('/\s*\(.*\)$/', '', trim($name))));
    }
}
