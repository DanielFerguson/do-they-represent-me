<?php

namespace App\Models;

use Database\Factories\PolicyAgreementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['policy_id', 'subject_type', 'subject_id', 'votes_same', 'votes_same_strong', 'votes_differ', 'votes_differ_strong', 'votes_absent', 'votes_absent_strong', 'agreement', 'category', 'computed_at'])]
class PolicyAgreement extends Model
{
    /** @use HasFactory<PolicyAgreementFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return BelongsTo<Policy, $this>
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class);
    }

    /**
     * The member or party this agreement score describes.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'agreement' => 'float',
            'computed_at' => 'datetime',
        ];
    }
}
