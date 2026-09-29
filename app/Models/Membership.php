<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 */
#[Fillable(['member_id', 'house_id', 'electorate_id', 'party_id', 'starts_on', 'ends_on', 'start_reason', 'end_reason'])]
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<House, $this>
     */
    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
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
     * Memberships held on the given date (start and end dates are inclusive).
     *
     * @param  Builder<Membership>  $query
     */
    #[Scope]
    protected function activeOn(Builder $query, CarbonInterface $date): void
    {
        $query->whereDate('starts_on', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
