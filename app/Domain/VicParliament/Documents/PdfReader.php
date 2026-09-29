<?php

namespace App\Domain\VicParliament\Documents;

use RuntimeException;
use Smalot\PdfParser\Parser;

/**
 * Extracts paragraphs from a PDF, for the rare document published without a
 * .docx version. Uses a pure-PHP parser because Laravel Cloud has no
 * poppler binaries.
 *
 * PDF text arrives as wrapped lines, so paragraphs are rebuilt from blank
 * lines and a few extraction artefacts are repaired (drop-cap splits such
 * as "A" / "YES 56", and stray whitespace after hyphens in names).
 */
class PdfReader
{
    public function __construct(public int $maxFileBytes = 20 * 1024 * 1024) {}

    /**
     * @return list<string>
     */
    public function paragraphs(string $path): array
    {
        if (! is_file($path) || filesize($path) > $this->maxFileBytes) {
            throw new RuntimeException("Document [{$path}] is missing or exceeds the size limit.");
        }

        $lines = preg_split('/\R/u', (new Parser)->parseFile($path)->getText()) ?: [];
        $paragraphs = [];
        $current = '';
        $pendingLetter = '';

        foreach ($lines as $line) {
            $line = trim((string) preg_replace('/\s+/u', ' ', $line));

            if ($line === '') {
                $this->flush($paragraphs, $current);

                continue;
            }

            if (preg_match('/^\p{Lu}$/u', $line) === 1) {
                $pendingLetter = $line;

                continue;
            }

            $line = $pendingLetter.$line;
            $pendingLetter = '';
            $current = $current === '' ? $line : (str_ends_with($current, '-') ? $current.$line : $current.' '.$line);
        }

        $this->flush($paragraphs, $current);

        return $paragraphs;
    }

    /**
     * @param  list<string>  $paragraphs
     */
    private function flush(array &$paragraphs, string &$current): void
    {
        if ($current !== '') {
            $paragraphs[] = (string) preg_replace('/(\p{L})- (\p{L})/u', '$1-$2', $current);
        }

        $current = '';
    }
}
