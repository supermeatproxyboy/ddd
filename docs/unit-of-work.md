# Journal Unit of Work

`app/Journal/Application/UnitOfWork/UnitOfWork.php` tracks explicitly registered changes to journal aggregates and domain events. `RecordEntryCommand`, `ReviseEntryCommand`, and `ForgetEntryCommand` all use it for writes.

The Unit of Work is a framework-independent application service. Its dependencies are `EntryRepository`, `DomainEventOutbox`, and `TransactionManager`. The Laravel service provider creates a fresh instance for each resolution, so separate commands do not share pending changes.

## Register, then commit

- `persist(Entry)` registers an insert or update. Registering the same instance again produces one save.
- `remove(Entry)` registers deletion of a persisted entry.
- `recordDomainEvent(DomainEvent)` registers an already-created event for outbox storage.
- `recordDomainEventAfterPersisting(Entry, callable)` registers an event factory for an entry already scheduled for persistence. The factory receives the saved aggregate, including its database-assigned identity.
- `commit()` flushes registered work in one transaction and returns a `CommitResult`.
- `CommitResult::persistedEntry($originalEntry)` returns the saved instance corresponding to an explicitly registered entry.
- `hasPendingChanges()` reports whether work is waiting to be committed.
- `discard()` clears pending work without writing to the database.

Registration does not write to the database or invoke event factories. Inside `commit()`, the Unit of Work saves registered entries, materializes their events, removes registered entries, and appends the events to the outbox. `TransactionManager` commits these writes together or rolls them all back.

```php
$entry = Entry::record(
    ownerId: $ownerId,
    content: $content,
    today: $clock->today(),
);
$recordedAt = $clock->now();

$unitOfWork->persist($entry);
$unitOfWork->recordDomainEventAfterPersisting(
    entry: $entry,
    domainEventFactory: static fn (Entry $persistedEntry): EventRecordedDomainEvent => EventRecordedDomainEvent::fromEntry(
        entry: $persistedEntry,
        recordedAt: $recordedAt,
    ),
);

$commitResult = $unitOfWork->commit();
$recordedEntry = $commitResult->persistedEntry($entry);
```

The original new entry retains its nullable identity. Use the persisted instance returned through `CommitResult` for subsequent operations. A commit result only resolves entries registered in that specific commit.

## Lifecycle and failures

Pending work is cleared after both successful and failed commits. A second empty commit performs no writes and does not replay events. Reusing the service requires registering fresh work explicitly.

An event factory or outbox failure rolls back entry inserts, updates, deletions, and outbox writes in the same database transaction. The Unit of Work also clears its queue when persistence or transaction setup fails. It does not silently retry failed work.

Database rollback does not restore mutable domain objects already revised in memory. After a failed operation, reload or deliberately reconstruct and re-register the intended changes.

An unsaved entry cannot be removed. Scheduling the same identity for both persistence and removal, registering two different instances of one identity, and registering duplicate event IDs are rejected rather than silently choosing an operation. Event factories cannot register more work or recursively commit while a flush is in progress.

The Unit of Work tracks explicit registrations. Callers mark changed aggregates with `persist()`; it does not scan all loaded objects for property changes. The lower-level `TransactionManager` remains responsible for the database transaction, while the Unit of Work owns pending changes and flushing.

## Verification

```sh
vendor/bin/phpunit tests/Unit/Journal/UnitOfWorkTest.php
php artisan test --filter=JournalUnitOfWorkTest
composer ci:check
```

Unit tests exercise deferred persistence, removal, discard, coalescing, registration rules, event staging, and commit-result lookup without booting Laravel. Database tests verify multi-entry commits, rollback of earlier writes when a later write fails, rollback of updates and deletions when outbox storage fails, event-factory failures, queue cleanup, and isolation between container resolutions.
