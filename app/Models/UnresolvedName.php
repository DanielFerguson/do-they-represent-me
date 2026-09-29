<?php

namespace App\Models;

use App\Enums\VoteValue;
use Database\Factories\UnresolvedNameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['division_id', 'raw_name', 'side', 'resolved_member_id', 'resolved_at'])]
class UnresolvedName extends Model
{
    /** @use HasFactory<UnresolvedNameFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function resolvedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'resolved_member_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'side' => VoteValue::class,
            'resolved_at' => 'datetime',
        ];
    }
}
