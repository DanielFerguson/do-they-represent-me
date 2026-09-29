<?php

namespace App\Domain\Policies\Importing;

/**
 * The tabs of the policy curation workbook that the importer reads. Rows are
 * keyed by their row number in the sheet, and cells by column heading.
 */
final readonly class PolicyWorkbook
{
    public const POLICIES = 'Policies';

    public const POLICY_VOTES = 'Policy votes';

    public const DIVISIONS = 'Divisions';

    /**
     * Column headings the importer needs on each tab.
     *
     * @var array<string, list<string>>
     */
    public const REQUIRED_HEADINGS = [
        self::POLICIES => ['ID', 'Status', 'Topic', 'Policy title', 'Question (neutral wording)', '"Agree" means', 'Why this question', 'To verify before publishing', 'Reviewer notes', 'Description', 'Arguments for', 'Arguments against', 'Sources'],
        self::POLICY_VOTES => ['Policy ID', 'Division ID', 'Agree when vote is', 'Strong?', 'Rationale (public)'],
        self::DIVISIONS => ['Division ID', 'Ayes', 'Noes'],
    ];

    /**
     * @param  array<int, array<string, string>>  $policies
     * @param  array<int, array<string, string>>  $policyVotes
     * @param  array<int, array<string, string>>  $divisions
     */
    public function __construct(
        public array $policies,
        public array $policyVotes,
        public array $divisions,
    ) {}
}
