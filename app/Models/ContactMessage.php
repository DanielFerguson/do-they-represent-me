<?php

namespace App\Models;

use App\Enums\ContactTopic;
use Database\Factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A message sent through the public contact form. Messages are deleted a
 * year after they arrive, as the privacy page promises.
 *
 * @property ContactTopic $topic
 * @property string|null $name
 * @property string $email
 * @property string|null $context
 * @property string|null $context_url
 * @property string $message
 * @property Carbon|null $handled_at
 * @property Carbon $created_at
 */
#[Fillable(['topic', 'name', 'email', 'context', 'context_url', 'message', 'handled_at'])]
class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory, MassPrunable;

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<=', now()->subYear());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'topic' => ContactTopic::class,
            'handled_at' => 'datetime',
        ];
    }
}
