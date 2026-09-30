<?php

namespace App\Domain\Candidates;

use RuntimeException;

/**
 * Every problem found in a candidate list, so they can all be fixed in one
 * pass. Nothing is imported while any remain.
 */
class InvalidCandidateList extends RuntimeException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(PHP_EOL, $errors));
    }
}
