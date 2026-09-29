<?php

namespace App\Models;

use Database\Factories\StanceSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A published version of the quiz data. The payload is the exact JSON body
 * served to browsers, and the hash is its SHA-256, so a shared link keeps
 * working after the data changes.
 *
 * @property string $payload
 * @property Carbon|null $published_at
 */
#[Fillable(['hash', 'payload', 'created_at', 'published_at'])]
class StanceSnapshot extends Model
{
    /** @use HasFactory<StanceSnapshotFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
