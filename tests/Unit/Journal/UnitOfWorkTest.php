<?php

namespace Tests\Unit\Journal;

use App\Journal\Application\Transactions\TransactionManager;
use App\Journal\Application\UnitOfWork\UnitOfWork;
use App\Journal\Domain\Entries\Entry;
use App\Journal\Domain\Entries\EntryContent;
use App\Journal\Domain\Entries\EntryKind;
use App\Journal\Domain\Entries\JournalDate;
use App\Journal\Domain\Events\EventRecordedDomainEvent;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryDomainEventOutbox;
use Tests\Doubles\InMemoryEntryRepository;

class UnitOfWorkTest extends TestCase
{
    private InMemoryEntryRepository $entriesRepository;

    private InMemoryDomainEventOutbox $domainEventOutbox;

    private UnitOfWork $unitOfWork;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entriesRepository = new InMemoryEntryRepository;
        $this->domainEventOutbox = new InMemoryDomainEventOutbox;
        $transactionManager = $this->createMock(TransactionManager::class);
        $transactionManager->method('transactional')->willReturnCallback(fn (callable $operation) => $operation());
        $this->unitOfWork = new UnitOfWork(
            entriesRepository: $this->entriesRepository,
            domainEventOutbox: $this->domainEventOutbox,
            transactionManager: $transactionManager,
        );
    }

    public function test_entries_and_events_are_deferred_until_commit_and_not_replayed(): void
    {
        $entry = $this->newEntry();
        $this->unitOfWork->persist($entry);
        $this->unitOfWork->persist($entry);
        $this->unitOfWork->recordDomainEventAfterPersisting($entry, function (Entry $persistedEntry): EventRecordedDomainEvent {
            $this->assertNotNull($persistedEntry->id());

            return EventRecordedDomainEvent::fromEntry($persistedEntry, new DateTimeImmutable('2026-11-08T18:00:00Z'));
        });

        $this->assertTrue($this->unitOfWork->hasPendingChanges());
        $this->assertSame(0, $this->entriesRepository->count());
        $this->assertCount(0, $this->domainEventOutbox->events);

        $result = $this->unitOfWork->commit();
        $persistedEntry = $result->persistedEntry($entry);
        $this->assertNotNull($persistedEntry->id());
        $this->assertSame(1, $this->entriesRepository->count());
        $this->assertCount(1, $this->domainEventOutbox->events);
        $this->assertFalse($this->unitOfWork->hasPendingChanges());

        $this->unitOfWork->commit();
        $this->assertSame(1, $this->entriesRepository->count());
        $this->assertCount(1, $this->domainEventOutbox->events);
    }

    public function test_removals_are_deferred_until_commit(): void
    {
        $entry = $this->entriesRepository->save($this->newEntry());
        $this->unitOfWork->remove($entry);

        $this->assertSame(1, $this->entriesRepository->count());
        $this->unitOfWork->commit();
        $this->assertSame(0, $this->entriesRepository->count());
        $this->assertFalse($this->unitOfWork->hasPendingChanges());
    }

    public function test_discard_removes_staged_entries_and_event_factories_without_writing(): void
    {
        $entry = $this->newEntry();
        $this->unitOfWork->persist($entry);
        $this->unitOfWork->recordDomainEventAfterPersisting($entry, function (): never {
            $this->fail('A discarded event factory must not be invoked.');
        });

        $this->unitOfWork->discard();
        $this->unitOfWork->commit();

        $this->assertFalse($this->unitOfWork->hasPendingChanges());
        $this->assertSame(0, $this->entriesRepository->count());
        $this->assertCount(0, $this->domainEventOutbox->events);
    }

    public function test_contradictory_save_and_remove_registrations_are_rejected(): void
    {
        $entry = $this->entriesRepository->save($this->newEntry());
        $this->unitOfWork->persist($entry);
        $this->expectException(LogicException::class);

        $this->unitOfWork->remove($entry);
    }

    public function test_two_instances_of_the_same_identity_cannot_silently_overwrite_each_other(): void
    {
        $entry = $this->entriesRepository->save($this->newEntry());
        $this->unitOfWork->persist($entry);
        $this->expectException(LogicException::class);

        $this->unitOfWork->persist(clone $entry);
    }

    public function test_event_factories_require_an_entry_registered_for_persistence(): void
    {
        $this->expectException(LogicException::class);

        $this->unitOfWork->recordDomainEventAfterPersisting($this->newEntry(), function (): never {
            $this->fail('An unregistered event factory must not be invoked.');
        });
    }

    public function test_a_commit_result_cannot_return_an_entry_from_another_scope(): void
    {
        $entry = $this->newEntry();
        $this->unitOfWork->persist($entry);
        $result = $this->unitOfWork->commit();
        $this->expectException(LogicException::class);

        $result->persistedEntry($this->newEntry());
    }

    public function test_already_created_domain_events_are_also_staged_until_commit(): void
    {
        $entry = $this->entriesRepository->save($this->newEntry());
        $domainEvent = EventRecordedDomainEvent::fromEntry($entry, new DateTimeImmutable('2026-11-08T18:00:00Z'));
        $this->unitOfWork->recordDomainEvent($domainEvent);

        $this->assertCount(0, $this->domainEventOutbox->events);
        $this->unitOfWork->commit();
        $this->assertSame($domainEvent, $this->domainEventOutbox->events[$domainEvent->eventId()]);
    }

    private function newEntry(): Entry
    {
        return Entry::record(7, new EntryContent('A meaningful day', EntryKind::Event,
            JournalDate::fromString('2026-11-08'), 'A story worth remembering.'), JournalDate::fromString('2026-11-08'));
    }
}
