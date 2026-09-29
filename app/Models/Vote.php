<?php

namespace App\Models;

use App\Enums\VoteValue;
use Database\Factories\VoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['division_id', 'member_id', 'party_id', 'vote', 'is_teller'])]
class Vote extends Model
{
    /** @use HasFactory<VoteFactory> */
    use HasFactory;

    public $timestamps = false;

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
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * The member's party on the day of the division.
     *
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vote' => VoteValue::class,
            'is_teller' => 'boolean',
        ];
    }
}
