<?php

namespace App\Models;

use Database\Factories\DivisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parliament_id', 'house_id', 'proceedings_document_id', 'sitting_number', 'sitting_date', 'sequence', 'body', 'item_number', 'item_title', 'question', 'stage', 'presiding_role', 'presiding_member_id', 'result', 'ayes_count', 'noes_count', 'summary', 'is_free_vote', 'needs_review'])]
class Division extends Model
{
    /** @use HasFactory<DivisionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Parliament, $this>
     */
    public function parliament(): BelongsTo
    {
        return $this->belongsTo(Parliament::class);
    }

    /**
     * @return BelongsTo<House, $this>
     */
    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    /**
     * @return BelongsTo<ProceedingsDocument, $this>
     */
    public function proceedingsDocument(): BelongsTo
    {
        return $this->belongsTo(ProceedingsDocument::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function presidingMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'presiding_member_id');
    }

    /**
     * @return HasMany<Vote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /**
     * @return HasMany<UnresolvedName, $this>
     */
    public function unresolvedNames(): HasMany
    {
        return $this->hasMany(UnresolvedName::class);
    }

    /**
     * @return HasMany<DivisionPartyPosition, $this>
     */
    public function partyPositions(): HasMany
    {
        return $this->hasMany(DivisionPartyPosition::class);
    }

    /**
     * @return HasMany<PolicyDivision, $this>
     */
    public function policyDivisions(): HasMany
    {
        return $this->hasMany(PolicyDivision::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sitting_date' => 'date',
            'is_free_vote' => 'boolean',
            'needs_review' => 'boolean',
        ];
    }
}
