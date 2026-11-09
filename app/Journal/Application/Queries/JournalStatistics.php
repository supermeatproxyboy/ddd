<?php

namespace App\Journal\Application\Queries;

final readonly class JournalStatistics
{
    public function __construct(public int $total, public int $days, public int $milestones) {}
}
