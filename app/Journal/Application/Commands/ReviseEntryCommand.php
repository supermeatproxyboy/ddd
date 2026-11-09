<?php

namespace App\Journal\Application\Commands;

use App\Journal\Application\UnitOfWork\UnitOfWork;
use App\Journal\Domain\Entries\Entry;
use App\Journal\Domain\Entries\EntryContent;
use App\Journal\Domain\Entries\EntryRepository;
use App\Journal\Domain\JournalClock;

final readonly class ReviseEntryCommand
{
    public function __construct(
        private EntryRepository $entriesRepository,
        private JournalClock $clock,
        private UnitOfWork $unitOfWork,
    ) {}

    public function handle(
        int $ownerId,
        int $entryId,
        EntryContent $content,
    ): Entry {
        $entry = $this
            ->entriesRepository
            ->getOwned($ownerId, $entryId);

        $entry->revise($content, $this->clock->today());

        $this
            ->unitOfWork
            ->persist($entry);

        $commitResult = $this
            ->unitOfWork
            ->commit();

        return $commitResult->persistedEntry($entry);
    }
}
