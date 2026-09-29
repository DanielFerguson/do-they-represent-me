<?php

namespace App\Domain\VicParliament\Parsing;

use Carbon\CarbonImmutable;

/**
 * Extracts divisions from the paragraphs of a Votes and Proceedings or
 * Minutes of the Proceedings document.
 *
 * Paragraph styles differ between houses and years, so the parser relies on
 * the printed wording, which is consistent:
 *
 *   No 131 — Tuesday 29 July 2025
 *   Question — That the Bill be now read a second time — put.
 *   The House divided (the Deputy Speaker, Matt Fregon, in the Chair) —
 *   Ayes 32
 *   Brad Battin; Jade Benham; … Peter Walsh.
 *   Noes 49
 *   …
 *   Question defeated.
 *
 * @phpstan-type DivisionHeader array{sittingNumber: int, sittingDate: CarbonImmutable, sequence: int, body: string, presidingRole: ?string, presidingOfficer: ?string, itemNumber: ?int, itemTitle: ?string, question: ?string}
 */
class ProceedingsParser
{
    private const SITTING_HEADING = '/^No\.?\s+(\d+)\s+—\s+(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday),?\s+(\d{1,2}\s+[A-Z][a-z]+\s+\d{4})$/u';

    private const DIVIDED = '/^The (House|Council|Committee) divided\b\s*(.*)$/u';

    private const TALLY = '/^(AYES|NOES|Ayes|Noes),?\s+(\d+)$/u';

    private const PRESIDING = '/the (Acting Speaker|Deputy Speaker|Speaker|Acting President|Deputy President|President|Acting Chair|Deputy Chair|Chair)(?:,\s*([^,]+?),)?\s+in the Chair/iu';

    private const ITEM_HEADING = '/^(\d{1,3})\s*(\p{Lu}.+?)\s*—/u';

    /**
     * Unnumbered bill headings, used by the Council's "Committee of the whole" supplement.
     */
    private const BILL_HEADING = "/^(?=.*\\bBILL \\d{4}$)[\\p{Lu}\\d\\s,’'()\\-–&.]+$/u";

    private const LOOKBEHIND = 12;

    private const NAME = "/^\p{Lu}[\p{L}’'\-]+(?: [\p{L}’'\-]+){1,3}$/u";

    private const TELLERS = '/\.?\s*Tellers?:\s*/u';

    /**
     * @param  list<string>  $paragraphs
     * @return list<ParsedDivision>
     */
    public function parse(array $paragraphs): array
    {
        $divisions = [];
        $sittingNumber = null;
        $sittingDate = null;
        $sequence = 0;
        $item = null;
        $header = null;
        $side = null;
        $tallies = ['ayes' => 0, 'noes' => 0];
        $names = $this->emptyNameLists();
        $tellers = [];

        foreach ($paragraphs as $index => $paragraph) {
            if (preg_match(self::SITTING_HEADING, $paragraph, $match)) {
                $number = (int) $match[1];

                if ($number !== $sittingNumber) {
                    $sittingNumber = $number;
                    $sittingDate = CarbonImmutable::createFromFormat('!j F Y', $match[2]) ?: null;
                    $sequence = 0;
                    $item = null;
                }

                continue;
            }

            if (preg_match(self::DIVIDED, $paragraph, $match)) {
                if ($header !== null) {
                    $divisions[] = $this->finish($header, $tallies, $names, $tellers, null);
                }

                $header = $sittingNumber !== null && $sittingDate !== null
                    ? $this->header($paragraphs, $index, $match[1], $match[2], $sittingNumber, $sittingDate, ++$sequence, $item)
                    : null;
                $side = null;
                $tallies = ['ayes' => 0, 'noes' => 0];
                $names = $this->emptyNameLists();
                $tellers = [];

                continue;
            }

            if ($header === null) {
                if (preg_match(self::ITEM_HEADING, $paragraph, $match)) {
                    $item = ['number' => (int) $match[1], 'title' => $match[2], 'index' => $index];
                } elseif (preg_match(self::BILL_HEADING, $paragraph)) {
                    $item = ['number' => null, 'title' => $paragraph, 'index' => $index];
                }

                continue;
            }

            if (preg_match(self::TALLY, $paragraph, $match)) {
                $side = strtolower($match[1]) === 'ayes' ? 'ayes' : 'noes';
                $tallies[$side] = (int) $match[2];

                continue;
            }

            if (str_starts_with($paragraph, '(Recorded by')) {
                continue;
            }

            if ($side !== null && ($list = $this->nameList($paragraph, $names[$side] === [])) !== null) {
                $names[$side] = [...$names[$side], ...$list['names'], ...$list['tellers']];
                $tellers = [...$tellers, ...$list['tellers']];

                continue;
            }

            if ($side === 'noes') {
                $divisions[] = $this->finish($header, $tallies, $names, $tellers, $paragraph);
                $header = null;
                $side = null;
            }
        }

        if ($header !== null) {
            $divisions[] = $this->finish($header, $tallies, $names, $tellers, null);
        }

        return $divisions;
    }

    /**
     * Gather the context printed before a division: the question put and the
     * numbered business item (usually naming the bill) it belongs to.
     *
     * @param  list<string>  $paragraphs
     * @param  array{number: ?int, title: string, index: int}|null  $item
     * @return DivisionHeader
     */
    private function header(array $paragraphs, int $index, string $body, string $chairText, int $sittingNumber, CarbonImmutable $sittingDate, int $sequence, ?array $item): array
    {
        $question = null;
        $earliest = max(0, $index - self::LOOKBEHIND, $item['index'] ?? 0);

        for ($cursor = $index - 1; $cursor >= $earliest; $cursor--) {
            if ($this->statesQuestion($paragraphs[$cursor])) {
                $question = $paragraphs[$cursor];

                break;
            }
        }

        if ($question === null && $item !== null && $item['index'] >= $index - self::LOOKBEHIND) {
            $question = $paragraphs[$item['index']];
        }

        preg_match(self::PRESIDING, $chairText, $presiding);

        return [
            'sittingNumber' => $sittingNumber,
            'sittingDate' => $sittingDate,
            'sequence' => $sequence,
            'body' => $body,
            'presidingRole' => isset($presiding[1]) ? ucwords(strtolower($presiding[1])) : null,
            'presidingOfficer' => isset($presiding[2]) ? trim($presiding[2]) : null,
            'itemNumber' => $item['number'] ?? null,
            'itemTitle' => $item['title'] ?? null,
            'question' => $question,
        ];
    }

    /**
     * Whether a paragraph states the question put, e.g. "Question — That the
     * Bill be now read a third time — put." or "Jane Citizen moved, That the
     * Bill be now read a third time and do pass." A bare "Question — put."
     * says nothing, so the search continues past it to the motion itself.
     */
    private function statesQuestion(string $paragraph): bool
    {
        if (preg_match('/\bThat\b/u', $paragraph) !== 1) {
            return false;
        }

        return str_starts_with($paragraph, 'Question')
            || preg_match('/\b(moved|question),? That\b/u', $paragraph) === 1;
    }

    /**
     * @param  DivisionHeader  $header
     * @param  array{ayes: int, noes: int}  $tallies
     * @param  array{ayes: list<string>, noes: list<string>}  $names
     * @param  list<string>  $tellers
     */
    private function finish(array $header, array $tallies, array $names, array $tellers, ?string $result): ParsedDivision
    {
        return new ParsedDivision(
            sittingNumber: $header['sittingNumber'],
            sittingDate: $header['sittingDate'],
            sequence: $header['sequence'],
            body: $header['body'],
            presidingRole: $header['presidingRole'],
            presidingOfficer: $header['presidingOfficer'],
            itemNumber: $header['itemNumber'],
            itemTitle: $header['itemTitle'],
            question: $header['question'],
            ayesCount: $tallies['ayes'],
            noesCount: $tallies['noes'],
            ayes: $names['ayes'],
            noes: $names['noes'],
            result: $result,
            tellers: $tellers,
        );
    }

    /**
     * @return array{ayes: list<string>, noes: list<string>}
     */
    private function emptyNameLists(): array
    {
        return ['ayes' => [], 'noes' => []];
    }

    /**
     * Interpret a paragraph as a list of member names, or return null if it
     * isn't one.
     *
     * Lists are normally semicolon separated and end with a full stop. Some
     * short lists use commas, and conscience votes append "Tellers: A; B."
     * A single name has no separator, so it is only accepted as the first
     * line under a tally.
     *
     * @return array{names: list<string>, tellers: list<string>}|null
     */
    private function nameList(string $paragraph, bool $isFirstLine): ?array
    {
        [$voters, $tellers] = array_pad(preg_split(self::TELLERS, $paragraph, 2) ?: [$paragraph], 2, '');

        if (str_contains($voters, ';')) {
            return ['names' => $this->splitNames($voters, ';'), 'tellers' => $this->splitNames($tellers, ';')];
        }

        $commaSeparated = $this->splitNames($voters, ',');

        if (count($commaSeparated) > 1 && $this->allNames($commaSeparated)) {
            return ['names' => $commaSeparated, 'tellers' => $this->splitNames($tellers, ';')];
        }

        if ($isFirstLine && $this->allNames($commaSeparated)) {
            return ['names' => $commaSeparated, 'tellers' => []];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function splitNames(string $text, string $separator): array
    {
        $names = [];

        foreach (explode($separator, $text) as $segment) {
            $segment = rtrim(trim($segment), '. ');
            $commaSeparated = array_map(trim(...), explode(',', $segment));

            if ($separator !== ',' && count($commaSeparated) > 1 && $this->allNames($commaSeparated)) {
                array_push($names, ...$commaSeparated);
            } elseif ($segment !== '') {
                $names[] = $segment;
            }
        }

        return $names;
    }

    /**
     * @param  list<string>  $candidates
     */
    private function allNames(array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if (preg_match(self::NAME, $candidate) !== 1) {
                return false;
            }
        }

        return $candidates !== [];
    }
}
