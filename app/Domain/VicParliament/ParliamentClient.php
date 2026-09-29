<?php

namespace App\Domain\VicParliament;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP access to the Parliament of Victoria website.
 *
 * Only allowlisted hosts are fetched (document URLs come from the site's own
 * JSON, so this guards against being pointed elsewhere), downloads are size
 * capped, and requests are paced and identify the application.
 */
class ParliamentClient
{
    private ?float $lastRequestAt = null;

    /**
     * @param  array<string, scalar|null>  $query
     * @return array<string, mixed>
     */
    public function getJson(string $path, array $query = []): array
    {
        $response = $this->request()->get($this->url($path), $query)->throw();

        return $response->json() ?? [];
    }

    /**
     * Download a document and return its raw bytes.
     */
    public function download(string $pathOrUrl): string
    {
        $response = $this->request()->timeout(120)->get($this->url($pathOrUrl))->throw();
        $body = $response->body();

        if (strlen($body) > config('services.parliament_vic.max_document_bytes')) {
            throw new RuntimeException("Document at [{$pathOrUrl}] exceeds the download size limit.");
        }

        return $body;
    }

    /**
     * Resolve a site-relative path and ensure the host is allowlisted.
     */
    public function url(string $pathOrUrl): string
    {
        $url = str_starts_with($pathOrUrl, 'http')
            ? $pathOrUrl
            : rtrim(config('services.parliament_vic.base_url'), '/').'/'.ltrim($pathOrUrl, '/');

        $host = parse_url($url, PHP_URL_HOST);

        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! in_array($host, config('services.parliament_vic.allowed_hosts'), true)) {
            throw new RuntimeException("Refusing to fetch [{$url}]: host is not allowlisted.");
        }

        return $url;
    }

    private function request(): PendingRequest
    {
        $this->pace();

        return Http::withUserAgent(config('services.parliament_vic.user_agent'))
            ->acceptJson()
            ->timeout(30)
            ->retry(3, fn (int $attempt): int => $attempt * 2000);
    }

    /**
     * Keep at least the configured delay between requests to the site.
     */
    private function pace(): void
    {
        $delay = config('services.parliament_vic.request_delay_ms') / 1000;

        if ($this->lastRequestAt !== null && $delay > 0) {
            $wait = $this->lastRequestAt + $delay - microtime(true);

            if ($wait > 0) {
                usleep((int) ($wait * 1_000_000));
            }
        }

        $this->lastRequestAt = microtime(true);
    }
}
