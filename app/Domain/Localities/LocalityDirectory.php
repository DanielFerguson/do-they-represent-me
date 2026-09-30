<?php

namespace App\Domain\Localities;

use App\Enums\ElectorateKind;
use App\Models\Electorate;
use App\Models\Locality;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The list of suburbs and localities the finder searches in the browser,
 * with the districts each one falls in. It is built once, cached, and
 * served under the hash of its content so browsers can keep it for a year.
 * vic:import-data clears it when the localities change.
 */
class LocalityDirectory
{
    private const BODY_KEY = 'localities.body';

    private const HASH_KEY = 'localities.hash';

    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;

    /**
     * The JSON body, built once and cached until the localities change.
     */
    public function body(): string
    {
        return Cache::rememberForever(self::BODY_KEY, fn (): string => json_encode($this->payload(), self::JSON_FLAGS));
    }

    /**
     * The SHA-256 of the body, cached separately so pages can link to the
     * file without loading it.
     */
    public function hash(): string
    {
        return Cache::rememberForever(self::HASH_KEY, fn (): string => hash('sha256', $this->body()));
    }

    /**
     * A site-relative link to the current version of the file.
     */
    public function url(): string
    {
        return route('localities.show', $this->hash(), absolute: false);
    }

    public function forget(): void
    {
        Cache::forget(self::BODY_KEY);
        Cache::forget(self::HASH_KEY);
    }

    /**
     * @return array{districts: array<string, string>, localities: list<array{name: string, postcodes: list<string>, districts: list<array{slug: string, share: float}>}>}
     */
    private function payload(): array
    {
        $districts = Electorate::query()->where('kind', ElectorateKind::District)->orderBy('name')->pluck('name', 'slug');

        $shares = DB::table('electorate_locality')
            ->join('electorates', 'electorates.id', '=', 'electorate_locality.electorate_id')
            ->where('electorates.kind', ElectorateKind::District->value)
            ->orderByDesc('electorate_locality.share')
            ->orderBy('electorates.slug')
            ->get(['electorate_locality.locality_id', 'electorates.slug', 'electorate_locality.share'])
            ->groupBy('locality_id');

        $localities = Locality::query()
            ->orderBy('name')
            ->orderBy('sal_code')
            ->get()
            ->filter(fn (Locality $locality): bool => $shares->has($locality->id))
            ->map(fn (Locality $locality): array => [
                'name' => $locality->name,
                'postcodes' => $locality->postcodes,
                'districts' => $shares->get($locality->id)
                    ->map(fn (object $share): array => ['slug' => (string) $share->slug, 'share' => round((float) $share->share, 4)])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return ['districts' => $districts->all(), 'localities' => $localities];
    }
}
