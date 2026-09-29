<?php

namespace App\Domain\VicParliament\Members;

/**
 * Normalises member names so that the same person matches regardless of
 * typographic differences between sources (curly apostrophes, dash
 * variants, honorifics, spacing and case).
 */
class NameNormalizer
{
    private const HONORIFICS = '/^(the hon\.?|hon\.?|mr\.?|mrs\.?|ms\.?|miss|dr\.?|prof\.?)\s+/u';

    public function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = str_replace(['’', '‘', '`', 'ʼ'], "'", $name);
        $name = str_replace(['‑', '–', '—', '−'], '-', $name);
        $name = (string) preg_replace(self::HONORIFICS, '', $name);
        $name = (string) preg_replace('/\s*-\s*/u', '-', $name);
        $name = (string) preg_replace('/[.,]/u', '', $name);

        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }
}
