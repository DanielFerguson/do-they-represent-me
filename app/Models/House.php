<?php

namespace App\Models;

use Database\Factories\HouseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'short_name', 'hansard_code', 'papers_code', 'seats'])]
class House extends Model
{
    /** @use HasFactory<HouseFactory> */
    use HasFactory;

    /**
     * @return HasMany<Electorate, $this>
     */
    public function electorates(): HasMany
    {
        return $this->hasMany(Electorate::class);
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return HasMany<Division, $this>
     */
    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }
}
