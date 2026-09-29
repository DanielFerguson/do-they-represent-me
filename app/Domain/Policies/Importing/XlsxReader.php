<?php

namespace App\Domain\Policies\Importing;

use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * Reads cell values from one worksheet of an .xlsx workbook.
 *
 * Streams the sheet XML with XMLReader (no network access, no entity
 * loading) and enforces size limits. Formula cells return their cached
 * value, or an empty string if the file was saved without one.
 */
class XlsxReader
{
    /**
     * Transitional and Strict OOXML spreadsheet namespaces.
     */
    private const SPREADSHEET_NAMESPACES = [
        'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
        'http://purl.oclc.org/ooxml/spreadsheetml/main',
    ];

    private const RELATIONSHIP_NAMESPACES = [
        'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
        'http://purl.oclc.org/ooxml/officeDocument/relationships',
    ];

    public function __construct(
        public int $maxFileBytes = 20 * 1024 * 1024,
        public int $maxEntryBytes = 100 * 1024 * 1024,
    ) {}

    /**
     * Read every non-empty row of the named sheet, keyed by row number, with
     * cells keyed by zero-based column index.
     *
     * @return array<int, array<int, string>>
     */
    public function rows(string $path, string $sheetName): array
    {
        $this->guardAgainstOversizedInput($path);

        $sheetPath = $this->sheetPath($path, $sheetName);
        $sharedStrings = $this->sharedStrings($path);
        $reader = $this->open($path, $sheetPath);

        $rows = [];
        $rowNumber = 0;
        $column = -1;
        $cell = null;
        $inPhonetic = false;

        while ($reader->read()) {
            if (! in_array($reader->namespaceURI, self::SPREADSHEET_NAMESPACES, true)) {
                continue;
            }

            if ($reader->nodeType === XMLReader::ELEMENT) {
                if ($reader->localName === 'row') {
                    $rowNumber = (int) ($reader->getAttribute('r') ?? $rowNumber + 1);
                    $column = -1;
                } elseif ($reader->localName === 'c') {
                    $reference = $reader->getAttribute('r');
                    $column = $reference !== null ? $this->columnIndex($reference) : $column + 1;
                    $cell = $reader->isEmptyElement ? null : ['type' => $reader->getAttribute('t') ?? 'n', 'value' => ''];
                } elseif ($cell !== null && $reader->localName === 'v') {
                    $cell['value'] = $reader->readString();
                } elseif ($cell !== null && $reader->localName === 't' && ! $inPhonetic) {
                    $cell['value'] .= $reader->readString();
                } elseif ($reader->localName === 'rPh' && ! $reader->isEmptyElement) {
                    $inPhonetic = true;
                }
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'rPh') {
                $inPhonetic = false;
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'c' && $cell !== null) {
                $value = $cell['type'] === 's' ? ($sharedStrings[(int) $cell['value']] ?? '') : $cell['value'];

                if ($value !== '') {
                    $rows[$rowNumber][$column] = $value;
                }

                $cell = null;
            }
        }

        $reader->close();

        return $rows;
    }

    /**
     * The names of the workbook's sheets, in tab order.
     *
     * @return list<string>
     */
    public function sheetNames(string $path): array
    {
        $this->guardAgainstOversizedInput($path);

        $reader = $this->open($path, 'xl/workbook.xml');
        $names = [];

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'sheet') {
                $names[] = (string) $reader->getAttribute('name');
            }
        }

        $reader->close();

        return $names;
    }

    /**
     * Find the worksheet part for a sheet name via the workbook relationships.
     */
    private function sheetPath(string $path, string $sheetName): string
    {
        $relationshipId = null;
        $reader = $this->open($path, 'xl/workbook.xml');

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'sheet' && $reader->getAttribute('name') === $sheetName) {
                foreach (self::RELATIONSHIP_NAMESPACES as $namespace) {
                    $relationshipId ??= $reader->getAttributeNs('id', $namespace);
                }

                break;
            }
        }

        $reader->close();

        if ($relationshipId === null) {
            throw new RuntimeException("Workbook [{$path}] has no sheet named [{$sheetName}].");
        }

        $reader = $this->open($path, 'xl/_rels/workbook.xml.rels');
        $target = null;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'Relationship' && $reader->getAttribute('Id') === $relationshipId) {
                $target = $reader->getAttribute('Target');

                break;
            }
        }

        $reader->close();

        if ($target === null) {
            throw new RuntimeException("Workbook [{$path}] has no worksheet for sheet [{$sheetName}].");
        }

        return str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(string $path): array
    {
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::RDONLY);
        $exists = $zip->statName('xl/sharedStrings.xml') !== false;
        $zip->close();

        if (! $exists) {
            return [];
        }

        $reader = $this->open($path, 'xl/sharedStrings.xml');
        $strings = [];
        $current = null;
        $inPhonetic = false;

        while ($reader->read()) {
            if (! in_array($reader->namespaceURI, self::SPREADSHEET_NAMESPACES, true)) {
                continue;
            }

            if ($reader->nodeType === XMLReader::ELEMENT) {
                if ($reader->localName === 'si') {
                    $current = '';

                    if ($reader->isEmptyElement) {
                        $strings[] = '';
                        $current = null;
                    }
                } elseif ($current !== null && $reader->localName === 't' && ! $inPhonetic) {
                    $current .= $reader->readString();
                } elseif ($reader->localName === 'rPh' && ! $reader->isEmptyElement) {
                    $inPhonetic = true;
                }
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'rPh') {
                $inPhonetic = false;
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'si' && $current !== null) {
                $strings[] = $current;
                $current = null;
            }
        }

        $reader->close();

        return $strings;
    }

    /**
     * Convert a cell reference such as "AB12" to a zero-based column index.
     */
    private function columnIndex(string $reference): int
    {
        $letters = (string) preg_replace('/[^A-Z]/', '', strtoupper($reference));
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function open(string $path, string $entry): XMLReader
    {
        $reader = XMLReader::open('zip://'.$path.'#'.$entry, null, LIBXML_NONET | LIBXML_COMPACT);

        if ($reader === false) {
            throw new RuntimeException("Unable to open [{$entry}] in workbook [{$path}].");
        }

        return $reader;
    }

    private function guardAgainstOversizedInput(string $path): void
    {
        if (! is_file($path) || filesize($path) > $this->maxFileBytes) {
            throw new RuntimeException("Workbook [{$path}] is missing or exceeds the size limit.");
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException("Workbook [{$path}] is not a valid .xlsx file.");
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->statIndex($index);

            if ($entry !== false && $entry['size'] > $this->maxEntryBytes) {
                $zip->close();

                throw new RuntimeException("Workbook [{$path}] contains a part that exceeds the size limit.");
            }
        }

        $zip->close();
    }
}
