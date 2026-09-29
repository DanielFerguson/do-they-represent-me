<?php

namespace App\Enums;

/**
 * Where a policy is in editorial review. Only published policies appear in
 * the quiz; the others are kept so reviewers can preview how they score.
 */
enum PolicyStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Published = 'published';
    case Dropped = 'dropped';

    /**
     * Map the workbook's Status column, where reviewers mark a policy "Ready"
     * to publish it.
     */
    public static function fromWorkbook(string $status): ?self
    {
        return match (strtolower(trim($status))) {
            '', 'draft' => self::Draft,
            'review' => self::Review,
            'ready' => self::Published,
            'dropped' => self::Dropped,
            default => null,
        };
    }
}
