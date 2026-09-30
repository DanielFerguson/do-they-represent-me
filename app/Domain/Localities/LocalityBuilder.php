<?php

namespace App\Domain\Localities;

use App\Domain\Policies\Importing\XlsxReader;
use RuntimeException;

/**
 * Works out which Assembly districts each Victorian suburb or locality falls
 * in, and what share of its residents live in each, from ABS data:
 *
 * - the ASGS Edition 3 allocation files, which place every mesh block (the
 *   smallest ABS area, a few dozen homes) in a suburb or locality (SAL), a
 *   postcode area (POA) and a state electoral division (SED);
 * - the 2021 Census mesh block counts, which give each block's residents.
 *
 * Residents are added up per locality and district, and per locality and
 * postcode. Localities with no recorded residents fall back to land area.
 * Shares under 1% are dropped as boundary slivers.
 *
 * @phpstan-type LocalityRow array{sal_code: string, locality: string, postcodes: list<string>, district: string, share: float}
 */
final class LocalityBuilder
{
    public const MIN_SHARE = 0.01;

    private const VICTORIA = '2';

    /**
     * Non-geographic ABS codes, which hold no real places.
     */
    private const NON_GEOGRAPHIC = ['no usual address', 'migratory', 'outside australia'];

    public function __construct(private XlsxReader $reader) {}

    /**
     * @param  array<string, string>  $districtRegions  each district's name => its region's name
     * @return list<LocalityRow>
     */
    public function build(string $sedPath, string $salPath, string $poaPath, string $countsPath, array $districtRegions): array
    {
        $blocks = $this->blockCounts($countsPath);
        $districts = $this->districts($sedPath, $districtRegions);
        $localities = $this->allocations($salPath, 'SAL_CODE_2021', 'SAL_NAME_2021');
        $postcodes = $this->allocations($poaPath, 'POA_CODE_2021', 'POA_NAME_2021');

        /** @var array<string, array{name: string, districts: array<string, array{0: float, 1: float}>, postcodes: array<string, array{0: float, 1: float}>}> $totals */
        $totals = [];

        foreach ($localities as $block => [$code, $name]) {
            if (! isset($districts[$block])) {
                continue;
            }

            [$people, $area] = $blocks[$block] ?? [0.0, 0.0];
            $totals[$code] ??= ['name' => self::cleanName($name), 'districts' => [], 'postcodes' => []];
            $totals[$code]['districts'][$districts[$block]] = self::add($totals[$code]['districts'][$districts[$block]] ?? null, $people, $area);

            if (isset($postcodes[$block]) && ctype_digit($postcodes[$block][0])) {
                $postcode = $postcodes[$block][0];
                $totals[$code]['postcodes'][$postcode] = self::add($totals[$code]['postcodes'][$postcode] ?? null, $people, $area);
            }
        }

        $rows = [];

        foreach ($totals as $code => $locality) {
            $postcodeShares = self::shares($locality['postcodes']);
            ksort($postcodeShares, SORT_STRING);
            $districtShares = self::shares($locality['districts']);
            arsort($districtShares);

            foreach ($districtShares as $district => $share) {
                $rows[] = [
                    'sal_code' => (string) $code,
                    'locality' => $locality['name'],
                    'postcodes' => array_map('strval', array_keys($postcodeShares)),
                    'district' => (string) $district,
                    'share' => round($share, 4),
                ];
            }
        }

        usort($rows, fn (array $a, array $b): int => [$a['locality'], $a['sal_code'], -$a['share'], $a['district']] <=> [$b['locality'], $b['sal_code'], -$b['share'], $b['district']]);

        return $rows;
    }

    /**
     * ABS names carry a state suffix where a name is used in more than one
     * state, e.g. "Abbotsford (Vic.)". Other qualifiers, which tell apart
     * two Victorian places of the same name, are kept.
     */
    public static function cleanName(string $name): string
    {
        $name = (string) preg_replace('/\s*\(Vic\.\)$/', '', trim($name));

        return (string) preg_replace('/\s*-\s*Vic\.\)$/', ')', $name);
    }

    /**
     * Residents and land area of each Victorian mesh block.
     *
     * @return array<string, array{0: float, 1: float}>
     */
    private function blockCounts(string $path): array
    {
        $blocks = [];

        foreach ($this->reader->sheetNames($path) as $sheet) {
            if (! str_starts_with($sheet, 'Table')) {
                continue;
            }

            $columns = null;

            foreach ($this->reader->eachRow($path, $sheet) as $cells) {
                if ($columns === null) {
                    if (in_array('MB_CODE_2021', $cells, true)) {
                        $columns = self::columns($cells, ['MB_CODE_2021', 'Person', 'AREA_ALBERS_SQKM'], $path);
                    }

                    continue;
                }

                $block = $cells[$columns['MB_CODE_2021']] ?? '';

                if (! ctype_digit($block)) {
                    continue;
                }

                if (! str_starts_with($block, self::VICTORIA)) {
                    break;
                }

                $blocks[$block] = [(float) ($cells[$columns['Person']] ?? 0), (float) ($cells[$columns['AREA_ALBERS_SQKM']] ?? 0)];
            }
        }

        if ($blocks === []) {
            throw new RuntimeException("[{$path}] has no Victorian mesh block counts.");
        }

        return $blocks;
    }

    /**
     * The district each Victorian mesh block is in, checked against the
     * districts and regions in electorates.csv.
     *
     * @param  array<string, string>  $districtRegions
     * @return array<string, string>
     */
    private function districts(string $path, array $districtRegions): array
    {
        $districts = [];
        $unknown = [];

        foreach ($this->allocations($path, 'SED_CODE_2025', 'SED_NAME_2025') as $block => [, $name]) {
            if (self::isNonGeographic($name)) {
                continue;
            }

            if (preg_match('/^(.+?) \((.+)\)$/', $name, $matches) !== 1 || ($districtRegions[$matches[1]] ?? null) !== $matches[2]) {
                $unknown[$name] = true;

                continue;
            }

            $districts[$block] = $matches[1];
        }

        if ($unknown !== []) {
            throw new RuntimeException('These ABS districts do not match electorates.csv: '.implode(', ', array_keys($unknown)).'.');
        }

        $missing = array_diff(array_keys($districtRegions), $districts);

        if ($missing !== []) {
            throw new RuntimeException('These districts have no mesh blocks in the ABS file: '.implode(', ', $missing).'.');
        }

        return $districts;
    }

    /**
     * Each Victorian mesh block's code and name in one allocation file.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function allocations(string $path, string $codeColumn, string $nameColumn): array
    {
        $allocations = [];
        $columns = null;

        foreach ($this->reader->eachRow($path, (string) ($this->reader->sheetNames($path)[0] ?? '')) as $cells) {
            if ($columns === null) {
                $columns = self::columns($cells, ['MB_CODE_2021', $codeColumn, $nameColumn], $path);

                continue;
            }

            $block = $cells[$columns['MB_CODE_2021']] ?? '';
            $name = $cells[$columns[$nameColumn]] ?? '';

            if (str_starts_with($block, self::VICTORIA) && ! self::isNonGeographic($name)) {
                $allocations[$block] = [$cells[$columns[$codeColumn]] ?? '', $name];
            }
        }

        return $allocations;
    }

    /**
     * @param  array<int, string>  $cells
     * @param  list<string>  $headings
     * @return array<string, int>
     */
    private static function columns(array $cells, array $headings, string $path): array
    {
        $columns = [];

        foreach ($headings as $heading) {
            $index = array_search($heading, $cells, true);

            if ($index === false) {
                throw new RuntimeException("[{$path}] has no {$heading} column.");
            }

            $columns[$heading] = (int) $index;
        }

        return $columns;
    }

    private static function isNonGeographic(string $name): bool
    {
        $name = strtolower($name);

        foreach (self::NON_GEOGRAPHIC as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{0: float, 1: float}|null  $total
     * @return array{0: float, 1: float}
     */
    private static function add(?array $total, float $people, float $area): array
    {
        return [($total[0] ?? 0.0) + $people, ($total[1] ?? 0.0) + $area];
    }

    /**
     * Each part's share of residents, or of land area where no residents are
     * recorded, leaving out shares under 1%.
     *
     * @param  array<string, array{0: float, 1: float}>  $parts  residents and area per part
     * @return array<string, float>
     */
    private static function shares(array $parts): array
    {
        $people = array_sum(array_column($parts, 0));
        $basis = $people > 0 ? 0 : 1;
        $total = $people > 0 ? $people : array_sum(array_column($parts, 1));

        if ($total <= 0) {
            return [];
        }

        $shares = [];

        foreach ($parts as $part => $values) {
            $share = $values[$basis] / $total;

            if ($share >= self::MIN_SHARE) {
                $shares[$part] = $share;
            }
        }

        return $shares;
    }
}
