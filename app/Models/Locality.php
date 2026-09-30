<?php

namespace App\Models;

use Database\Factories\LocalityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An ABS suburb or locality, with the share of its residents living in each
 * Assembly district. Built from ABS mesh block data by vic:build-localities.
 *
 * @property list<string> $postcodes
 */
#[Fillable(['sal_code', 'name', 'postcodes'])]
class Locality extends Model
{
    /** @use HasFactory<LocalityFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<Electorate, $this>
     */
    public function electorates(): BelongsToMany
    {
        return $this->belongsToMany(Electorate::class)->withPivot('share');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'postcodes' => 'array',
        ];
    }
}
