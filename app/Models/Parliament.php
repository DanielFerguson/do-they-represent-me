<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ParliamentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'starts_on', 'ends_on'])]
class Parliament extends Model
{
    /** @use HasFactory<ParliamentFactory> */
    use HasFactory;

    /**
     * @return HasMany<Division, $this>
     */
    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }

    /**
     * The parliament sitting on the given date.
     */
    public static function onDate(CarbonInterface $date): ?self
    {
        return static::query()
            ->where('starts_on', '<=', $date)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $date))
            ->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
