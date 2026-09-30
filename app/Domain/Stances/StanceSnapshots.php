<?php

namespace App\Domain\Stances;

use App\Models\Policy;
use App\Models\StanceSnapshot;
use Closure;

/**
 * Versions of the published quiz data. Each is stored as the exact JSON body
 * served to browsers and named by the SHA-256 of its content, so a shared
 * results link keeps the data it was made with.
 */
class StanceSnapshots
{
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;

    public function __construct(private StanceSnapshotBuilder $builder) {}

    /**
     * Store the current published data as the live version. Unchanged data
     * keeps its hash, and data that changes back to an earlier version makes
     * that version live again. Does nothing until a policy is published.
     */
    public function publish(): ?StanceSnapshot
    {
        if (! Policy::query()->published()->exists()) {
            return null;
        }

        [$hash, $body] = $this->encode($this->builder->build());

        return StanceSnapshot::query()->updateOrCreate(['hash' => $hash], ['payload' => $body, 'published_at' => now()]);
    }

    /**
     * The live version, or null while no policy is published.
     */
    public function current(): ?StanceSnapshot
    {
        if (! Policy::query()->published()->exists()) {
            return null;
        }

        return StanceSnapshot::query()->whereNotNull('published_at')->latest('published_at')->first();
    }

    /**
     * True when the published data has changed since the live version was
     * stored, so the site is showing out-of-date results.
     */
    public function isStale(): bool
    {
        if (! Policy::query()->published()->exists()) {
            return false;
        }

        return $this->current()?->hash !== $this->encode($this->builder->build())[0];
    }

    /**
     * The current data including policies still in review, for reviewers'
     * preview links. Built on request and never stored.
     *
     * @param  Closure(Policy): string  $policyUrl  the preview link to each policy's evidence page
     */
    public function previewBody(Closure $policyUrl): string
    {
        return $this->encode($this->builder->build(includeReview: true, policyUrl: $policyUrl))[1];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: string} the content hash and the JSON body, which carries the hash as its version
     */
    private function encode(array $payload): array
    {
        $hash = hash('sha256', json_encode($payload, self::JSON_FLAGS));

        return [$hash, json_encode(['version' => $hash, ...$payload], self::JSON_FLAGS)];
    }
}
