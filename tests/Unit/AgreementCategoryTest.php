<?php

use App\Enums\AgreementCategory;

it('places an agreement score in its band', function (float $agreement, AgreementCategory $category) {
    expect(AgreementCategory::forAgreement($agreement))->toBe($category);
})->with([
    'all agreeing' => [1.0, AgreementCategory::For3],
    'lower edge of consistently for' => [0.95, AgreementCategory::For3],
    'just below consistently for' => [0.9499, AgreementCategory::For2],
    'lower edge of almost always for' => [0.85, AgreementCategory::For2],
    'lower edge of generally for' => [0.60, AgreementCategory::For1],
    'just below generally for' => [0.5999, AgreementCategory::Mixture],
    'lower edge of mixed' => [0.40, AgreementCategory::Mixture],
    'lower edge of generally against' => [0.15, AgreementCategory::Against1],
    'lower edge of almost always against' => [0.05, AgreementCategory::Against2],
    'just below almost always against' => [0.0499, AgreementCategory::Against3],
    'none agreeing' => [0.0, AgreementCategory::Against3],
]);
