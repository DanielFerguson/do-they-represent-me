<?php

use App\Domain\VicParliament\Members\NameNormalizer;

it('treats typographic variants of the same name as equal', function (string $a, string $b) {
    $normalizer = new NameNormalizer;

    expect($normalizer->normalize($a))->toBe($normalizer->normalize($b));
})->with([
    'curly apostrophe' => ['Danny O’Brien', "Danny O'Brien"],
    'case and spacing' => ['  lily   D’AMBROSIO ', "Lily D'Ambrosio"],
    'dash variants' => ['Ann‑Marie Hermans', 'Ann-Marie Hermans'],
    'honorific' => ['Ms Mary-Anne Thomas', 'Mary-Anne Thomas'],
    'spaced hyphen' => ['Kathleen Matthews - Ward', 'Kathleen Matthews-Ward'],
]);

it('keeps different people apart', function () {
    $normalizer = new NameNormalizer;

    expect($normalizer->normalize('Tim Bull'))->not->toBe($normalizer->normalize('Josh Bull'));
});
