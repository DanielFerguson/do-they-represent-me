<?php

namespace App\Models;

use App\Enums\PartyPosition;
use Database\Factories\DivisionPartyPositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property PartyPosition $position
 */
#[Fillable(['division_id', 'party_id', 'ayes', 'noes', 'eligible', 'position'])]
class DivisionPartyPosition extends Model
{
    /** @use HasFactory<DivisionPartyPositionFactory> */
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
            'position' => PartyPosition::class,
        ];
    }
}
