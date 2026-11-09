<?php

namespace App\Journal\Domain\Entries;

use DomainException;

final class InvalidEntry extends DomainException
{
    /** @param array<string, string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }
}
