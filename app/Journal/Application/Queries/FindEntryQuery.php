<?php

namespace App\Journal\Application\Queries;

use App\Journal\Domain\Entries\EntryRepository;

final readonly class FindEntryQuery
{
    public function __construct(
        private EntryRepository $entriesRepository
    ) {}

    public function handle(
        int $ownerId,
        int $entryId
    ): EntryView {
        return EntryView::fromEntry(
            $this
                ->entriesRepository
                ->getOwned($ownerId, $entryId)
        );
    }
}
