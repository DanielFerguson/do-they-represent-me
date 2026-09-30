<?php

namespace App\Domain\VicParliament\ReferenceData;

use App\Domain\VicParliament\Members\NameNormalizer;
use App\Enums\ElectorateKind;
use App\Models\Election;
use App\Models\Electorate;
use App\Models\House;
use App\Models\Locality;
use App\Models\Member;
use App\Models\MemberAlias;
use App\Models\Membership;
use App\Models\Parliament;
use App\Models\Party;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SplFileObject;

/**
 * Loads the curated reference data in database/data/*.csv.
 *
 * The CSV files are the source of truth: records are matched on natural
 * keys and updated in place, and memberships and aliases missing from the
 * files are removed. Importing the same files twice changes nothing.
 */
class ReferenceDataImporter
{
    public function __construct(private NameNormalizer $normalizer) {}

    /**
     * @return array<string, int> Number of rows imported per file.
     */
    public function import(string $directory): array
    {
        return DB::transaction(fn (): array => [
            'houses' => $this->houses($directory),
            'parliaments' => $this->parliaments($directory),
            'parties' => $this->parties($directory),
            'electorates' => $this->electorates($directory),
            'members' => $this->members($directory),
            'memberships' => $this->memberships($directory),
            'member_aliases' => $this->aliases($directory),
            'localities' => $this->localities($directory),
            'elections' => $this->elections($directory),
        ]);
    }

    private function houses(string $directory): int
    {
        return $this->each($directory, 'houses.csv', function (array $row): void {
            House::query()->updateOrCreate(['slug' => $row['slug']], [
                'name' => $row['name'],
                'short_name' => $row['short_name'],
                'hansard_code' => (int) $row['hansard_code'],
                'papers_code' => (int) $row['papers_code'],
                'seats' => (int) $row['seats'],
            ]);
        });
    }

    private function parliaments(string $directory): int
    {
        return $this->each($directory, 'parliaments.csv', function (array $row): void {
            Parliament::query()->updateOrCreate(['number' => (int) $row['number']], [
                'starts_on' => $row['starts_on'],
                'ends_on' => $row['ends_on'] ?: null,
            ]);
        });
    }

    private function parties(string $directory): int
    {
        return $this->each($directory, 'parties.csv', function (array $row): void {
            Party::query()->updateOrCreate(['short_name' => $row['short_name']], [
                'display_name' => $row['display_name'],
                'name' => $row['name'],
                'slug' => $row['slug'],
                'colour' => $row['colour'] ?: null,
                'is_whipless' => $row['is_whipless'] === 'yes',
            ]);
        });
    }

    private function electorates(string $directory): int
    {
        $houses = House::query()->pluck('id', 'slug');

        return $this->each($directory, 'electorates.csv', function (array $row, int $line) use ($houses): void {
            $regionId = null;

            if ($row['region'] !== '') {
                $regionId = Electorate::query()->where('kind', ElectorateKind::Region)->where('name', $row['region'])->value('id')
                    ?? throw new RuntimeException("electorates.csv line {$line}: unknown region [{$row['region']}]. Regions must be listed before districts.");
            }

            Electorate::query()->updateOrCreate(['slug' => $row['slug']], [
                'house_id' => $houses[$row['house']] ?? throw new RuntimeException("electorates.csv line {$line}: unknown house [{$row['house']}]."),
                'kind' => ElectorateKind::from($row['kind']),
                'name' => $row['name'],
                'region_id' => $regionId,
            ]);
        });
    }

    private function members(string $directory): int
    {
        return $this->each($directory, 'members.csv', function (array $row): void {
            Member::query()->updateOrCreate(['slug' => $row['slug']], [
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'display_name' => $row['display_name'],
                'parliament_vic_id' => $row['parliament_vic_id'] === '' ? null : (int) $row['parliament_vic_id'],
                'profile_url' => $row['profile_url'] ?: null,
            ]);
        });
    }

    private function memberships(string $directory): int
    {
        $members = Member::query()->pluck('id', 'slug');
        $houses = House::query()->pluck('id', 'slug');
        $parties = Party::query()->pluck('id', 'short_name');
        $electorates = Electorate::query()->get(['id', 'house_id', 'name'])->keyBy(fn (Electorate $electorate): string => $electorate->house_id.'|'.$electorate->name);
        $kept = [];

        $count = $this->each($directory, 'memberships.csv', function (array $row, int $line) use ($members, $houses, $parties, $electorates, &$kept): void {
            $houseId = $houses[$row['house']] ?? throw new RuntimeException("memberships.csv line {$line}: unknown house [{$row['house']}].");

            $membership = Membership::query()->updateOrCreate([
                'member_id' => $members[$row['member']] ?? throw new RuntimeException("memberships.csv line {$line}: unknown member [{$row['member']}]."),
                'house_id' => $houseId,
                'starts_on' => $row['starts_on'],
            ], [
                'electorate_id' => $electorates[$houseId.'|'.$row['electorate']]->id ?? throw new RuntimeException("memberships.csv line {$line}: unknown electorate [{$row['electorate']}]."),
                'party_id' => $parties[$row['party']] ?? throw new RuntimeException("memberships.csv line {$line}: unknown party [{$row['party']}]."),
                'ends_on' => $row['ends_on'] ?: null,
                'start_reason' => $row['start_reason'] ?: null,
                'end_reason' => $row['end_reason'] ?: null,
            ]);

            $kept[] = $membership->id;
        });

        Membership::query()->whereNotIn('id', $kept)->delete();

        return $count;
    }

    private function aliases(string $directory): int
    {
        $members = Member::query()->pluck('id', 'slug');
        $kept = [];

        $count = $this->each($directory, 'member_aliases.csv', function (array $row, int $line) use ($members, &$kept): void {
            $alias = MemberAlias::query()->updateOrCreate(['normalized_name' => $this->normalizer->normalize($row['alias'])], [
                'member_id' => $members[$row['member']] ?? throw new RuntimeException("member_aliases.csv line {$line}: unknown member [{$row['member']}]."),
                'name' => $row['alias'],
            ]);

            $kept[] = $alias->id;
        });

        MemberAlias::query()->whereNotIn('id', $kept)->delete();

        return $count;
    }

    /**
     * Suburbs and localities, one row per locality and district it falls in,
     * written by vic:build-localities. Loaded in bulk, as there are about
     * 3,000 localities.
     */
    private function localities(string $directory): int
    {
        $districts = Electorate::query()->where('kind', ElectorateKind::District)->pluck('id', 'name');
        $localities = [];
        $shares = [];

        $count = $this->each($directory, 'localities.csv', function (array $row, int $line) use ($districts, &$localities, &$shares): void {
            $shares[] = [
                'sal_code' => $row['sal_code'],
                'electorate_id' => $districts[$row['district']] ?? throw new RuntimeException("localities.csv line {$line}: unknown district [{$row['district']}]."),
                'share' => (float) $row['share'],
            ];
            $localities[$row['sal_code']] = [
                'sal_code' => $row['sal_code'],
                'name' => $row['locality'],
                'postcodes' => json_encode(array_values(array_filter(explode(' ', $row['postcodes']))), JSON_THROW_ON_ERROR),
            ];
        });

        $now = now();

        foreach (array_chunk(array_values($localities), 500) as $chunk) {
            Locality::query()->upsert(
                array_map(fn (array $locality): array => [...$locality, 'created_at' => $now, 'updated_at' => $now], $chunk),
                ['sal_code'],
                ['name', 'postcodes', 'updated_at'],
            );
        }

        Locality::query()->whereNotIn('sal_code', array_map('strval', array_keys($localities)))->delete();

        $ids = Locality::query()->pluck('id', 'sal_code');
        DB::table('electorate_locality')->delete();

        foreach (array_chunk($shares, 1000) as $chunk) {
            DB::table('electorate_locality')->insert(array_map(fn (array $share): array => [
                'locality_id' => $ids[$share['sal_code']],
                'electorate_id' => $share['electorate_id'],
                'share' => $share['share'],
            ], $chunk));
        }

        return $count;
    }

    private function elections(string $directory): int
    {
        return $this->each($directory, 'elections.csv', function (array $row): void {
            Election::query()->updateOrCreate(['slug' => $row['slug']], [
                'name' => $row['name'],
                'held_on' => $row['held_on'],
            ]);
        });
    }

    /**
     * Call the callback for each data row, keyed by the header row.
     *
     * @param  callable(array<string, string>, int): void  $callback
     */
    private function each(string $directory, string $file, callable $callback): int
    {
        $path = rtrim($directory, '/').'/'.$file;

        if (! is_file($path)) {
            throw new RuntimeException("Reference data file [{$path}] is missing.");
        }

        $csv = new SplFileObject($path);
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
        $header = null;
        $count = 0;

        foreach ($csv as $index => $fields) {
            if (! is_array($fields) || $fields === [null]) {
                continue;
            }

            if ($header === null) {
                $header = $fields;

                continue;
            }

            $callback(array_combine($header, array_map(fn (?string $value): string => trim((string) $value), $fields)), $index + 1);
            $count++;
        }

        return $count;
    }
}
