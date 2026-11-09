<?php

namespace App\Journal\Application\Commands;

use App\Journal\Application\UnitOfWork\UnitOfWork;
use App\Journal\Domain\Entries\EntryRepository;

final readonly class ForgetEntryCommand
{
    public function __construct(
        private EntryRepository $entriesRepository,
        private UnitOfWork $unitOfWork,
    ) {}

    public function handle(
        int $ownerId,
        int $entryId,
    ): void {
        $entry = $this
            ->entriesRepository
            ->getOwned($ownerId, $entryId);

        $this
            ->unitOfWork
            ->remove($entry);

        $this
            ->unitOfWork
            ->commit();
    }
}
