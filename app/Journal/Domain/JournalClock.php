<?php

namespace App\Journal\Domain;

use App\Journal\Domain\Entries\JournalDate;
use DateTimeImmutable;

interface JournalClock
{
    public function today(): JournalDate;

    public function now(): DateTimeImmutable;
}
