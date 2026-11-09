<?php

namespace App\Journal\Application\Queries;

final readonly class JournalPage
{
    /** @param list<EntryView> $entries */
    public function __construct(
        public array $entries,
        public int $total,
        public int $page,
        public int $perPage,
        public JournalStatistics $statistics,
    ) {}
}
