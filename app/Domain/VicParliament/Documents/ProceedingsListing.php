<?php

namespace App\Domain\VicParliament\Documents;

use App\Domain\VicParliament\ParliamentClient;
use App\Models\House;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Lists a house's Votes and Proceedings (Assembly) or Minutes of the
 * Proceedings (Council) via the Parliament's house papers search.
 */
class ProceedingsListing
{
    private const PAGE_SIZE = 100;

    private const MINUTES_DOCUMENT_TYPE = 25;

    public function __construct(private ParliamentClient $client) {}

    /**
     * Documents covering sittings on or after the given date, newest first.
     *
     * @return Collection<int, ListedDocument>
     */
    public function since(House $house, CarbonInterface $since): Collection
    {
        $documents = [];

        for ($page = 1; ; $page++) {
            $hits = $this->client->getJson('/api/search/house-papers', [
                'document-type' => self::MINUTES_DOCUMENT_TYPE,
                'house' => $house->papers_code,
                'sortType' => 2,
                'page' => $page,
                'pageSize' => self::PAGE_SIZE,
            ])['result']['hits'] ?? [];

            foreach ($hits as $hit) {
                $document = $this->fromHit($hit);

                if ($document->endsOn !== null && $document->endsOn->lt($since)) {
                    return collect($documents);
                }

                $documents[] = $document;
            }

            if (count($hits) < self::PAGE_SIZE) {
                return collect($documents);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $hit
     */
    public function fromHit(array $hit): ListedDocument
    {
        $links = [];

        foreach ((array) ($hit['buttons'] ?? []) as $button) {
            if (is_array($button) && isset($button['suffix'], $button['href'])) {
                $links[$button['suffix']] ??= (string) $button['href'];
            }
        }

        $docxUrl = $links['doc'] ?? null;
        $pdfUrl = $links['pdf'] ?? null;

        preg_match('#/(house-paper-\d+)/#', (string) ($docxUrl ?? $pdfUrl), $folder);

        [$startsOn, $endsOn] = $this->dates((string) ($hit['meta'] ?? ''));
        $numbers = $this->sittingNumbers((string) ($hit['title'] ?? ''));

        return new ListedDocument(
            sourceKey: $folder[1] ?? (string) $hit['id'],
            title: (string) ($hit['title'] ?? ''),
            startsOn: $startsOn,
            endsOn: $endsOn,
            docxUrl: $docxUrl,
            pdfUrl: $pdfUrl,
            firstSittingNumber: $numbers === [] ? null : min($numbers),
            lastSittingNumber: $numbers === [] ? null : max($numbers),
        );
    }

    /**
     * "29 July 2025 - 31 July 2025" or "24 September 2026".
     *
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function dates(string $meta): array
    {
        $dates = array_map(
            fn (string $date): ?CarbonImmutable => CarbonImmutable::createFromFormat('!j F Y', trim($date)) ?: null,
            array_filter(explode(' - ', $meta)),
        );

        $startsOn = $dates[0] ?? null;

        return [$startsOn, $dates[1] ?? $startsOn];
    }

    /**
     * "Votes and Proceedings Nos 131 to 134" or "Minutes of the Proceedings Nos. 128, 129 and 130".
     *
     * @return list<int>
     */
    private function sittingNumbers(string $title): array
    {
        if (! preg_match('/\bNos?\.?\s+(.+)$/u', $title, $match)) {
            return [];
        }

        preg_match_all('/\d+/', $match[1], $numbers);

        return array_map(intval(...), $numbers[0]);
    }
}
