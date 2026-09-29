<?php

namespace App\Models;

use Database\Factories\PolicyImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One successful import of the policy workbook: an audit trail of which
 * file (by SHA-256) the published results came from, and who uploaded it.
 *
 * @property array{policies_by_status: array<string, int>, links: int, removed: int} $summary
 * @property Carbon $created_at
 */
#[Fillable(['sha256', 'path', 'user_id', 'summary'])]
class PolicyImport extends Model
{
    /** @use HasFactory<PolicyImportFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'summary' => 'array',
        ];
    }
}
