<?php

namespace App\Models;

use App\Enums\VoteValue;
use Database\Factories\PolicyDivisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['policy_id', 'division_id', 'direction', 'is_strong', 'rationale'])]
class PolicyDivision extends Model
{
    /** @use HasFactory<PolicyDivisionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Policy, $this>
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class);
    }

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => VoteValue::class,
            'is_strong' => 'boolean',
        ];
    }
}
