<?php

namespace App\Journal\Infrastructure\Time;

use App\Journal\Domain\Entries\JournalDate;
use App\Journal\Domain\JournalClock;
use DateTimeImmutable;

final class LaravelJournalClock implements JournalClock
{
    public function today(): JournalDate
    {
        return JournalDate::fromString(today()->toDateString());
    }

    public function now(): DateTimeImmutable
    {
        return now()->toDateTimeImmutable();
    }
}
