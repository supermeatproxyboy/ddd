<?php

namespace App\Journal\Application\UnitOfWork;

use App\Journal\Application\Outbox\DomainEventOutbox;
use App\Journal\Application\Transactions\TransactionManager;
use App\Journal\Domain\Entries\Entry;
use App\Journal\Domain\Entries\EntryRepository;
use App\Journal\Domain\Events\DomainEvent;
use LogicException;

final class UnitOfWork
{
    /** @var array<string, Entry> */
    private array $entriesToPersist = [];

    /** @var array<string, Entry> */
    private array $entriesToRemove = [];

    /** @var array<string, DomainEvent> */
    private array $domainEvents = [];

    /** @var array<string, list<callable(Entry): DomainEvent>> */
    private array $domainEventFactories = [];

    private bool $committing = false;

    public function __construct(
        private readonly EntryRepository $entriesRepository,
        private readonly DomainEventOutbox $domainEventOutbox,
        private readonly TransactionManager $transactionManager,
    ) {}

    public function persist(Entry $entry): void
    {
        $this->ensureNotCommitting();

        $key = $this->entryKey($entry);

        if (isset($this->entriesToRemove[$key]))
        {
            throw new LogicException('An entry scheduled for removal cannot also be persisted.');
        }

        if (isset($this->entriesToPersist[$key]) && $this->entriesToPersist[$key] !== $entry) {
            throw new LogicException('Only one instance of an entry can be registered in a Unit of Work.');
        }

        $this->entriesToPersist[$key] = $entry;
    }

    public function remove(Entry $entry): void
    {
        $this->ensureNotCommitting();

        if ($entry->id() === null) {
            throw new LogicException('An unsaved entry cannot be scheduled for removal.');
        }

        $key = $this->entryKey($entry);

        if (isset($this->entriesToPersist[$key])) {
            throw new LogicException('An entry scheduled for persistence cannot also be removed.');
        }

        if (isset($this->entriesToRemove[$key]) && $this->entriesToRemove[$key] !== $entry) {
            throw new LogicException('Only one instance of an entry can be registered in a Unit of Work.');
        }

        $this->entriesToRemove[$key] = $entry;
    }

    public function recordDomainEvent(DomainEvent $domainEvent): void
    {
        $this->ensureNotCommitting();

        if (isset($this->domainEvents[$domainEvent->eventId()])) {
            throw new LogicException('This domain event is already registered.');
        }

        $this->domainEvents[$domainEvent->eventId()] = $domainEvent;
    }

    /** @param callable(Entry): DomainEvent $domainEventFactory */
    public function recordDomainEventAfterPersisting(
        Entry $entry,
        callable $domainEventFactory,
    ): void
    {
        $this->ensureNotCommitting();
        $key = $this->entryKey($entry);

        if (($this->entriesToPersist[$key] ?? null) !== $entry) {
            throw new LogicException('Register the entry for persistence before registering its domain event.');
        }

        $this->domainEventFactories[$key][] = $domainEventFactory;
    }

    public function hasPendingChanges(): bool
    {
        return $this->entriesToPersist !== []
            || $this->entriesToRemove !== []
            || $this->domainEvents !== []
            || $this->domainEventFactories !== [];
    }

    public function discard(): void
    {
        $this->ensureNotCommitting();
        $this->clearPendingChanges();
    }

    public function commit(): CommitResult
    {
        $this->ensureNotCommitting();

        if (! $this->hasPendingChanges()) {
            return new CommitResult([]);
        }

        $this->committing = true;

        try {
            return $this->transactionManager->transactional(function (): CommitResult {
                $persistedEntries = [];
                $domainEvents = $this->domainEvents;

                foreach ($this->entriesToPersist as $key => $entry) {
                    $persistedEntry = $this->entriesRepository->save($entry);
                    $persistedEntries[] = ['original' => $entry, 'persisted' => $persistedEntry];

                    foreach ($this->domainEventFactories[$key] ?? [] as $domainEventFactory) {
                        $domainEvent = $domainEventFactory($persistedEntry);

                        if (isset($domainEvents[$domainEvent->eventId()])) {
                            throw new LogicException('This domain event is already registered.');
                        }

                        $domainEvents[$domainEvent->eventId()] = $domainEvent;
                    }
                }

                foreach ($this->entriesToRemove as $entry) {
                    $this->entriesRepository->delete($entry);
                }

                foreach ($domainEvents as $domainEvent) {
                    $this->domainEventOutbox->append($domainEvent);
                }

                return new CommitResult($persistedEntries);
            });
        } finally {
            $this->clearPendingChanges();
            $this->committing = false;
        }
    }

    private function entryKey(Entry $entry): string
    {
        return $entry->id() === null
            ? 'new:'.spl_object_id($entry)
            : 'persisted:'.$entry->ownerId().':'.$entry->id();
    }

    private function ensureNotCommitting(): void
    {
        if ($this->committing) {
            throw new LogicException('A Unit of Work cannot be changed or committed recursively while it is committing.');
        }
    }

    private function clearPendingChanges(): void
    {
        $this->entriesToPersist = [];
        $this->entriesToRemove = [];
        $this->domainEvents = [];
        $this->domainEventFactories = [];
    }
}
