<?php

namespace App\Domain\VicParliament\Documents;

use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * Extracts plain-text paragraphs from a .docx file.
 *
 * Streams word/document.xml with XMLReader (no network access, no entity
 * loading) and enforces size limits, because the documents come from a
 * third-party site.
 */
class DocxReader
{
    private const WORD_NAMESPACE = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public function __construct(
        public int $maxFileBytes = 20 * 1024 * 1024,
        public int $maxDocumentXmlBytes = 50 * 1024 * 1024,
    ) {}

    /**
     * Read every non-empty paragraph, with whitespace collapsed.
     *
     * @return list<string>
     */
    public function paragraphs(string $path): array
    {
        $this->guardAgainstOversizedInput($path);

        $reader = XMLReader::open('zip://'.$path.'#word/document.xml', null, LIBXML_NONET | LIBXML_COMPACT);

        if ($reader === false) {
            throw new RuntimeException("Unable to open document XML in [{$path}].");
        }

        $paragraphs = [];

        /** @var list<string> $buffers Stack of in-progress paragraphs; text boxes can nest paragraphs. */
        $buffers = [];

        while ($reader->read()) {
            if ($reader->namespaceURI !== self::WORD_NAMESPACE) {
                continue;
            }

            if ($reader->nodeType === XMLReader::ELEMENT) {
                if ($reader->localName === 'p' && ! $reader->isEmptyElement) {
                    $buffers[] = '';
                } elseif ($buffers !== [] && $reader->localName === 't') {
                    $buffers[array_key_last($buffers)] .= $reader->readString();
                } elseif ($buffers !== [] && in_array($reader->localName, ['tab', 'br', 'cr'], true)) {
                    $buffers[array_key_last($buffers)] .= ' ';
                } elseif ($buffers !== [] && $reader->localName === 'noBreakHyphen') {
                    $buffers[array_key_last($buffers)] .= '-';
                }
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'p') {
                $text = trim((string) preg_replace('/\s+/u', ' ', (string) array_pop($buffers)));

                if ($text !== '') {
                    $paragraphs[] = $text;
                }
            }
        }

        $reader->close();

        return $paragraphs;
    }

    private function guardAgainstOversizedInput(string $path): void
    {
        if (! is_file($path) || filesize($path) > $this->maxFileBytes) {
            throw new RuntimeException("Document [{$path}] is missing or exceeds the size limit.");
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException("Document [{$path}] is not a valid .docx archive.");
        }

        $entry = $zip->statName('word/document.xml');
        $zip->close();

        if ($entry === false || $entry['size'] > $this->maxDocumentXmlBytes) {
            throw new RuntimeException("Document [{$path}] has no document body or it exceeds the size limit.");
        }
    }
}
