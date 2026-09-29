<?php

namespace App\Models;

use App\Enums\PolicyStatus;
use Database\Factories\PolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property PolicyStatus $status
 * @property Carbon|null $published_at
 * @property list<array{subject_type: string, subject_id: int, note: string}>|null $display_notes
 */
#[Fillable(['number', 'slug', 'title', 'question', 'description', 'rationale', 'arguments_for', 'arguments_against', 'sources', 'verification_notes', 'reviewer_notes', 'display_notes', 'topic', 'agree_means', 'status', 'published_at'])]
class Policy extends Model
{
    /** @use HasFactory<PolicyFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return HasMany<PolicyDivision, $this>
     */
    public function policyDivisions(): HasMany
    {
        return $this->hasMany(PolicyDivision::class);
    }

    /**
     * @return BelongsToMany<Division, $this>
     */
    public function divisions(): BelongsToMany
    {
        return $this->belongsToMany(Division::class, 'policy_divisions')
            ->withPivot(['direction', 'is_strong', 'rationale'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<PolicyAgreement, $this>
     */
    public function agreements(): HasMany
    {
        return $this->hasMany(PolicyAgreement::class);
    }

    /**
     * @param  Builder<Policy>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', PolicyStatus::Published);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PolicyStatus::class,
            'display_notes' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
