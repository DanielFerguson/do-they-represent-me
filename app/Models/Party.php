<?php

namespace App\Models;

use Database\Factories\PartyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['name', 'short_name', 'display_name', 'slug', 'colour', 'is_whipless'])]
class Party extends Model
{
    /** @use HasFactory<PartyFactory> */
    use HasFactory;

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return MorphMany<PolicyAgreement, $this>
     */
    public function policyAgreements(): MorphMany
    {
        return $this->morphMany(PolicyAgreement::class, 'subject');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_whipless' => 'boolean',
        ];
    }
}
