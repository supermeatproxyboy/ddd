<?php

namespace App\Journal\Application\Queries;

final readonly class ReadJournalQuery
{
    public function __construct(
        private JournalReader $journalReader,
    ) {}

    public function handle(
        int $ownerId,
        JournalFilters $filters,
        int $page = 1,
    ): JournalPage {
        return $this
            ->journalReader
            ->read($ownerId, $filters, $page);
    }
}
