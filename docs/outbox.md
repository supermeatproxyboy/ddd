# Domain event outbox

`EventRecordedDomainEvent` captures the fact that a journal entry was recorded. It applies to all three entry kinds: event, accomplishment, and achievement. The event is an immutable snapshot, with its own UUID, schema version, entry identity, owner identity, recording timestamp, and content.

See the [recording sequence diagram](architecture.md#recording-sequence) for the transaction, entry persistence, event creation, and outbox writes.

## Recording flow

`RecordEntryCommand` explicitly coordinates the operation:

1. Record the `Entry` aggregate and capture the recording timestamp.
2. Register the entry and its `EventRecordedDomainEvent` factory with `UnitOfWork`.
3. Call `UnitOfWork::commit()` to flush the pending work through `TransactionManager`.
4. Persist the entry, create its event with the saved identity, and append the event through `DomainEventOutbox` inside that transaction.
5. Return the saved entry through `CommitResult` after the transaction commits.

If domain validation, entry persistence, event serialization, or outbox storage fails, the transaction rolls back. The database implementation uses the same default Laravel database connection as the entry repository. Keep both stores on that connection to preserve atomicity.

The command registers an event factory, which the Unit of Work invokes after persistence has assigned the entry's integer identity. Reconstituting or reading an aggregate does not emit an event. Revisions and deletions also use the Unit of Work but do not yet produce additional event types.

The [Unit of Work](unit-of-work.md) defers registered repository writes and outbox events until commit and clears pending changes on success or failure. `TransactionManager`, implemented by `LaravelTransactionManager`, supplies the underlying database transaction.

## Storage contract

The `outbox_messages` table stores:

| Column           | Meaning                                                                                                   |
| ---------------- | --------------------------------------------------------------------------------------------------------- |
| `event_id`       | UUID v4 primary key, generated once for this domain event                                                 |
| `event_type`     | Stable discriminator: `EventRecordedDomainEvent`                                                          |
| `event_version`  | Payload schema version, initially `1`                                                                     |
| `aggregate_type` | `journal_entry`                                                                                           |
| `aggregate_id`   | Saved entry's identity                                                                                    |
| `owner_id`       | Account owning the entry                                                                                  |
| `payload`        | JSON snapshot containing `entry_id`, `owner_id`, `title`, `kind`, `occurred_on`, `body`, and `reflection` |
| `occurred_at`    | Time the recording event happened, stored in UTC with microseconds                                        |
| `published_at`   | `null` while the message awaits publishing                                                                |

`occurred_at` is the time the journal entry was recorded. `payload.occurred_on` is the calendar day the life event happened, which may be earlier.

The primary key prevents appending the same event ID twice. Separate command invocations create separate entries and events; this is not command-request deduplication.

The database adapter serializes explicit JSON fields with `JSON_THROW_ON_ERROR`. Consumers can identify the event by its stable discriminator and schema version without depending on a PHP class name or PHP object serialization.

Messages retain the original content after an entry is revised or deleted. Their aggregate identity is not a foreign key to `entries`, so a recorded fact survives deletion of its entry. The owner foreign key cascades on account deletion, removing journal content copied into outbox payloads along with that account's other journal data.

## Pending messages

This initial implementation stores pending events. A delivery worker, destinations, retries, and publication acknowledgments can be added around this storage contract.

To inspect pending messages directly in the application database:

```sql
SELECT event_id, event_type, event_version, aggregate_id, occurred_at
FROM outbox_messages
WHERE published_at IS NULL
ORDER BY occurred_at, event_id;
```

## Verification

```sh
vendor/bin/phpunit tests/Unit/Journal
php artisan test --filter=JournalOutboxTest
composer ci:check
```

The database tests verify atomic rollback even when the outbox row was inserted before an exception, entry-write and validation failures, immutable snapshots, uniqueness, category coverage, and account deletion. Unit tests verify event identity, UTC timestamps, content snapshots, and use cases without booting Laravel.
