<?php

namespace App\Models;

use App\Enums\ElectorateKind;
use Database\Factories\ElectorateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['house_id', 'kind', 'name', 'slug', 'region_id'])]
class Electorate extends Model
{
    /** @use HasFactory<ElectorateFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<House, $this>
     */
    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    /**
     * The Legislative Council region an Assembly district sits within.
     *
     * @return BelongsTo<Electorate, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Electorate::class, 'region_id');
    }

    /**
     * @return HasMany<Electorate, $this>
     */
    public function districts(): HasMany
    {
        return $this->hasMany(Electorate::class, 'region_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ElectorateKind::class,
        ];
    }
}
