<?php

namespace App\Models;

use Database\Factories\ProceedingsDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['house_id', 'source_key', 'title', 'first_sitting_number', 'last_sitting_number', 'starts_on', 'ends_on', 'docx_url', 'pdf_url', 'raw_path', 'sha256', 'divisions_count', 'fetched_at', 'parsed_at', 'parse_error'])]
class ProceedingsDocument extends Model
{
    /** @use HasFactory<ProceedingsDocumentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<House, $this>
     */
    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    /**
     * @return HasMany<Division, $this>
     */
    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'fetched_at' => 'datetime',
            'parsed_at' => 'datetime',
        ];
    }
}
