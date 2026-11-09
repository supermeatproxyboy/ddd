<?php

namespace App\Journal\Domain\Entries;

use RuntimeException;

final class EntryNotFound extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The journal entry was not found.');
    }
}
