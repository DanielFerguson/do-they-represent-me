<?php

namespace App\Models;

use Database\Factories\CandidateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A candidate on the ballot for a district or region. The party is linked
 * only when it has a record in this Parliament, and the member only when the
 * candidate sat in it.
 */
#[Fillable(['election_id', 'electorate_id', 'ballot_group', 'ballot_position', 'given_names', 'surname', 'ballot_party', 'party_id', 'member_id'])]
class Candidate extends Model
{
    /** @use HasFactory<CandidateFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Election, $this>
     */
    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    /**
     * @return BelongsTo<Electorate, $this>
     */
    public function electorate(): BelongsTo
    {
        return $this->belongsTo(Electorate::class);
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * The name as printed on the ballot paper: surname first, in capitals.
     */
    public function ballotName(): string
    {
        return mb_strtoupper($this->surname).', '.$this->given_names;
    }
}
