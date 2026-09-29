<?php

namespace App\Models;

use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['parliament_vic_id', 'first_name', 'last_name', 'display_name', 'slug', 'profile_url'])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return HasMany<MemberAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(MemberAlias::class);
    }

    /**
     * @return HasMany<Vote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /**
     * @return MorphMany<PolicyAgreement, $this>
     */
    public function policyAgreements(): MorphMany
    {
        return $this->morphMany(PolicyAgreement::class, 'subject');
    }
}
