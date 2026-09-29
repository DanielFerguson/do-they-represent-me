<?php

namespace App\Domain\Policies\Importing;

use RuntimeException;

/**
 * Every problem found in a policy workbook, so reviewers can fix them in one
 * pass. Nothing is imported while any remain.
 */
class InvalidPolicyWorkbook extends RuntimeException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(PHP_EOL, $errors));
    }
}
