<?php

namespace App\Journal\Application\Commands;

use App\Journal\Application\UnitOfWork\UnitOfWork;
use App\Journal\Domain\Entries\Entry;
use App\Journal\Domain\Entries\EntryContent;
use App\Journal\Domain\Events\EventRecordedDomainEvent;
use App\Journal\Domain\JournalClock;

final readonly class RecordEntryCommand
{
    public function __construct(
        private JournalClock       $clock,
        private UnitOfWork         $unitOfWork,
    ) {}

    public function handle(
        int          $ownerId,
        EntryContent $content,
    ): Entry
    {
        $entry = Entry::record(
            ownerId: $ownerId,
            content: $content,
            today: $this->clock->today(),
        );

        $recordedAt = $this->clock->now();

        $this
            ->unitOfWork
            ->persist($entry);

        $this
            ->unitOfWork
            ->recordDomainEventAfterPersisting(
                entry: $entry,
                domainEventFactory: static fn (Entry $recordedEntry): EventRecordedDomainEvent => EventRecordedDomainEvent::fromEntry(
                    entry:      $recordedEntry,
                    recordedAt: $recordedAt,
                ),
            );

        $commitResult = $this
            ->unitOfWork
            ->commit();

        return $commitResult->persistedEntry($entry);
    }
}
