# DDD architecture diagrams

These diagrams describe the current implementation and the default local configuration. They use [C4's context, container, component, and code views](https://c4model.com/diagrams), expressed in Mermaid flowcharts and a class diagram. A sequence diagram explains the transactional recording flow.

The code blocks are the editable diagram sources. Open this file in a Mermaid-enabled Markdown preview to render them, or paste an individual block into a Mermaid editor.

| View                                                                 | Question it answers                                   |
| -------------------------------------------------------------------- | ----------------------------------------------------- |
| [C1: System context](#c1-system-context)                             | Who uses DDD, and for what?                           |
| [C2: Containers](#c2-containers)                                     | What runs where, and where is data stored?            |
| [C3: Laravel components](#c3-laravel-components)                     | Which backend components collaborate?                 |
| [C4: Recording code and contracts](#c4-recording-code-and-contracts) | Which classes and interfaces implement recording?     |
| [Recording sequence](#recording-sequence)                            | How do an entry and its domain event commit together? |

## C1: System context

DDD is a personal journal. Each authenticated, verified account records its own meaningful events, accomplishments, and achievements and revisits them through a private timeline.

```mermaid
flowchart LR
    owner["Journal owner<br/>Person<br/>Wants to remember meaningful days"]
    system["DDD<br/>Software system<br/>Private journal, timeline, search, and reflections"]

    owner -->|"Records, revisits, revises, and deletes moments"| system

    classDef person fill:#224b69,color:#fff,stroke:#16354b
    classDef system fill:#326050,color:#fff,stroke:#24483c
    class owner person
    class system system
```

Account registration, verification, password recovery, two-factor authentication, and passkeys are capabilities of the same system. The default mail driver writes verification and recovery messages to a local log.

## C2: Containers

Here, a container means a runtime application or data store. The browser client and Laravel backend are parts of the same product; the database is an embedded SQLite file in the default configuration.

```mermaid
flowchart TB
    owner["Journal owner<br/>Person"]

    subgraph ddd["DDD software system"]
        browser["Browser client<br/>Vue 3, TypeScript, Inertia<br/>Journal, entry forms, authentication, and settings"]
        backend["Web application<br/>PHP / Laravel 13<br/>HTTP adapters, Fortify, and journaling bounded context"]
        database[("Application database<br/>SQLite: database/database.sqlite<br/>Accounts, entries, outbox, sessions, cache, and jobs")]
    end

    owner -->|"Uses"| browser
    browser -->|"HTTP requests and form submissions with session cookies"| backend
    backend -->|"Inertia page data, HTML, redirects, and validation errors"| browser
    backend -->|"Reads and writes through Laravel database connections"| database

    classDef person fill:#224b69,color:#fff,stroke:#16354b
    classDef container fill:#326050,color:#fff,stroke:#24483c
    classDef data fill:#f2e5c6,color:#29261f,stroke:#a78b50
    class owner person
    class browser,backend container
    class database data
```

The entry and outbox tables share one database connection so their writes can be atomic. Outbox messages remain pending with `published_at = null`.

`composer dev` additionally starts Vite for development assets and a Laravel queue worker. The queue worker processes Laravel jobs; an outbox publishing worker has not been implemented. Local mail is written to `storage/logs/laravel.log`. These supporting development details are separate from the core container view above.

Sources: [frontend bootstrap](../resources/js/app.ts), [HTTP routes](../routes/web.php), [default configuration](../.env.example), and [outbox schema](../database/migrations/2026_11_09_000001_create_outbox_messages_table.php).

## C3: Laravel components

This view zooms into the Laravel application. **Arrows show runtime calls**, with interface names on the relevant relationships. The application classes import those interfaces; the infrastructure implementations are supplied by `JournalServiceProvider`.

```mermaid
flowchart TB
    browser["Vue / Inertia client<br/>External to the backend container"]
    database[("SQLite database<br/>users, entries, outbox_messages, and supporting tables")]

    subgraph backend["Laravel web application"]
        authentication["Authentication and identity<br/>Fortify, User, and settings controllers"]
        http["Journal HTTP adapter<br/>Routes, auth/verified middleware,<br/>EntryController, and form requests"]

        subgraph application["Journal application"]
            commands["Write use cases<br/>RecordEntryCommand<br/>ReviseEntryCommand<br/>ForgetEntryCommand"]
            queries["Read use cases<br/>FindEntryQuery<br/>ReadJournalQuery"]
            work["UnitOfWork<br/>Stages entries and domain events<br/>Flushes pending work on commit"]
        end

        subgraph domain["Journal domain"]
            aggregate["Entry aggregate and values<br/>EntryContent, EntryKind, JournalDate<br/>Content, identity, and date rules"]
            event["EventRecordedDomainEvent<br/>Immutable, versioned recording snapshot"]
        end

        subgraph infrastructure["Journal infrastructure"]
            repository["EloquentEntryRepository<br/>Owner-scoped aggregate persistence"]
            reader["EloquentJournalReader<br/>Search, filters, pagination, and statistics"]
            outbox["DatabaseDomainEventOutbox<br/>Versioned JSON event storage"]
            transactions["LaravelTransactionManager<br/>Database transaction boundary"]
            clock["LaravelJournalClock<br/>Today's date and recording time"]
        end
    end

    browser -->|"Registration, login, and account settings"| authentication
    authentication -->|"Account and credential persistence"| database
    browser -->|"Authenticated journal HTTP requests"| http
    http -->|"Invokes a named command"| commands
    http -->|"Invokes a named query"| queries
    commands -->|"Records or revises"| aggregate
    commands -->|"Registers writes and event factories, then commits"| work
    work -->|"Invokes registered factories after persistence"| event
    commands -->|"Revise / Forget: EntryRepository.getOwned"| repository
    work -->|"EntryRepository.save / delete"| repository
    queries -->|"FindEntryQuery: EntryRepository"| repository
    queries -->|"ReadJournalQuery: JournalReader"| reader
    work -->|"DomainEventOutbox"| outbox
    work -->|"TransactionManager"| transactions
    commands -->|"JournalClock"| clock
    http -->|"JournalClock: entry-form default date"| clock
    repository -->|"Maps EntryRecord to and from Entry"| aggregate
    repository -->|"Owner-scoped SQL reads and writes"| database
    reader -->|"Owner-scoped SQL reads"| database
    outbox -->|"INSERT outbox_messages"| database
    transactions -->|"BEGIN, COMMIT, and ROLLBACK"| database

    classDef adapter fill:#edf2f7,color:#172b40,stroke:#7890a5
    classDef application fill:#326050,color:#fff,stroke:#24483c
    classDef domain fill:#f2e5c6,color:#29261f,stroke:#a78b50
    class authentication,http,repository,reader,outbox,transactions,clock adapter
    class commands,queries,work application
    class aggregate,event domain
```

- Authentication middleware supplies the verified account identity to the journal HTTP adapter. The journal domain represents its owner by integer identity.
- `FindEntryQuery` loads an owned aggregate through `EntryRepository` and maps it to `EntryView`. `ReadJournalQuery` uses `JournalReader` to obtain read models directly.
- All write commands stage their changes with `UnitOfWork` and call `commit()`. Only `RecordEntryCommand` registers `EventRecordedDomainEvent`. Revisions and deletions do not emit this event.
- The journal domain and application layers have no Laravel, Eloquent, or Inertia dependencies. See the [dependency direction diagram](domain-driven-design.md#responsibilities-and-dependencies) for source-level dependencies.

Sources: [HTTP adapter](../app/Http/Controllers/EntryController.php), [container bindings](../app/Providers/JournalServiceProvider.php), and [bounded-context guide](domain-driven-design.md).

## C4: Recording code and contracts

This code view focuses on the active recording command. Dotted dependency arrows point from a caller to an abstraction; implementation arrows point from an adapter to the interface it implements.

```mermaid
classDiagram
    direction TB

    class RecordEntryCommand {
        +handle(int ownerId, EntryContent content) Entry
    }
    class UnitOfWork {
        +persist(Entry entry) void
        +remove(Entry entry) void
        +recordDomainEvent(DomainEvent domainEvent) void
        +recordDomainEventAfterPersisting(Entry entry, callable domainEventFactory) void
        +commit() CommitResult
        +discard() void
        +hasPendingChanges() bool
    }
    class CommitResult {
        +persistedEntry(Entry originalEntry) Entry
    }
    class Entry {
        +record(int ownerId, EntryContent content, JournalDate today) Entry
        +id() ?int
        +ownerId() int
        +content() EntryContent
    }
    class Entity {
        <<abstract>>
        +sameIdentityAs(Entity other) bool
    }
    class AggregateRoot {
        <<abstract>>
        +aggregateType() string
    }
    class EntryRepository {
        <<interface>>
        +getOwned(int ownerId, int entryId) Entry
        +save(Entry entry) Entry
        +delete(Entry entry) void
    }
    class JournalClock {
        <<interface>>
        +today() JournalDate
        +now() DateTimeImmutable
    }
    class TransactionManager {
        <<interface>>
        +transactional(callable operation) mixed
    }
    class DomainEventOutbox {
        <<interface>>
        +append(DomainEvent domainEvent) void
    }
    class DomainEvent {
        <<interface>>
        +eventId() string
        +eventType() string
        +eventVersion() int
        +aggregateType() string
        +aggregateId() int
        +ownerId() int
        +occurredAt() DateTimeImmutable
        +payload() array
    }
    class EventRecordedDomainEvent {
        +fromEntry(Entry entry, DateTimeImmutable recordedAt) EventRecordedDomainEvent
        +payload() array
    }
    class EloquentEntryRepository
    class LaravelJournalClock
    class LaravelTransactionManager
    class DatabaseDomainEventOutbox

    RecordEntryCommand ..> Entry : records
    Entry --|> AggregateRoot
    AggregateRoot --|> Entity
    RecordEntryCommand ..> UnitOfWork : registers work and commits
    RecordEntryCommand ..> JournalClock : gets dates and time
    RecordEntryCommand ..> EventRecordedDomainEvent : registers factory
    UnitOfWork ..> EntryRepository : flushes aggregate writes
    UnitOfWork ..> TransactionManager : coordinates atomic writes
    UnitOfWork ..> DomainEventOutbox : flushes events
    UnitOfWork ..> CommitResult : returns saved aggregates
    DomainEventOutbox ..> DomainEvent : accepts
    EventRecordedDomainEvent ..|> DomainEvent
    EloquentEntryRepository ..|> EntryRepository
    LaravelJournalClock ..|> JournalClock
    LaravelTransactionManager ..|> TransactionManager
    DatabaseDomainEventOutbox ..|> DomainEventOutbox
```

`Entry::id()` is nullable before persistence. `EventRecordedDomainEvent::fromEntry()` requires a saved identity. `UnitOfWork::commit()` returns a `CommitResult`, which resolves the command's original entry to its saved instance. The Unit of Work clears its queue on success or failure.

The [domain abstraction diagram](domain-abstractions.md) expands the shared `ValueObject`, `Entity`, and `AggregateRoot` hierarchy and shows which journal types implement each role.

Sources: [RecordEntryCommand](../app/Journal/Application/Commands/RecordEntryCommand.php), [Entry](../app/Journal/Domain/Entries/Entry.php), [EventRecordedDomainEvent](../app/Journal/Domain/Events/EventRecordedDomainEvent.php), and [service-provider bindings](../app/Providers/JournalServiceProvider.php).

## Recording sequence

The command stages work before the Unit of Work starts a transaction on the shared database connection. The success branch expands the flush callback; the failure branch represents an exception at any point in that callback, including after the outbox insert.

```mermaid
sequenceDiagram
    autonumber
    participant Browser as Vue / Inertia client
    participant HTTP as Journal HTTP adapter
    participant Command as RecordEntryCommand
    participant Clock as JournalClock
    participant Aggregate as Entry
    participant Work as UnitOfWork
    participant Transaction as LaravelTransactionManager
    participant Repository as EloquentEntryRepository
    participant Event as EventRecordedDomainEvent
    participant Outbox as DatabaseDomainEventOutbox
    participant Database as SQLite connection

    Browser->>HTTP: POST /entries with session and form data
    HTTP->>HTTP: Authenticate, verify, validate, construct EntryContent
    HTTP->>Command: handle(ownerId, content)
    Command->>Clock: today()
    Clock-->>Command: JournalDate
    Command->>Aggregate: record(ownerId, content, today)
    Aggregate-->>Command: Entry with no persisted ID
    Command->>Clock: now()
    Clock-->>Command: Recording timestamp
    Command->>Work: persist(entry)
    Command->>Work: recordDomainEventAfterPersisting(entry, factory)
    Note over Command,Work: Entries and events are staged without database writes
    Command->>Work: commit()
    Work->>Transaction: transactional(flush)
    Transaction->>Database: BEGIN transaction
    Transaction->>Work: Execute flush callback

    alt Flush succeeds
        Work->>Repository: save(entry)
        Repository->>Database: INSERT entries
        Database-->>Repository: Assigned entry ID
        Repository-->>Work: Reconstituted saved Entry
        Work->>Event: Invoke factory with saved Entry and captured timestamp
        Event-->>Work: Immutable event with UUID and snapshot
        Work->>Outbox: append(domainEvent)
        Outbox->>Database: INSERT outbox_messages with published_at = null
        Database-->>Outbox: Inserted
        Outbox-->>Work: Event stored in current transaction
        Work-->>Transaction: CommitResult from flush callback
        Transaction->>Database: COMMIT
        Database-->>Transaction: All writes committed
        Transaction-->>Work: CommitResult
        Work->>Work: Clear pending changes
        Work-->>Command: CommitResult
        Command->>Command: Resolve persistedEntry(originalEntry)
        Command-->>HTTP: Saved Entry
        HTTP-->>Browser: Redirect to dashboard and show success toast
    else Flush throws
        Note over Work,Database: Execution stops at the failing persistence, event factory, serialization, or storage step
        Work--xTransaction: Exception escapes flush callback
        Transaction->>Database: ROLLBACK
        Database-->>Transaction: Callback writes reverted
        Transaction--xWork: Rethrow exception
        Work->>Work: Clear pending changes
        Work--xCommand: Propagate exception
        Command--xHTTP: Propagate exception
        HTTP-->>Browser: Error response
    end
```

`LaravelTransactionManager` invokes the flush callback inside Laravel's database transaction. An exception escaping the callback causes rollback. `UnitOfWork` clears pending changes in both cases. HTTP and domain validation errors can be rejected before work is staged or a transaction begins.

See [the outbox contract](outbox.md) for payload versioning, deduplication boundaries, account-deletion behavior, and pending-message storage.

## Keeping the diagrams current

Update these views when adding a runtime process, a new bounded context, a command/query dependency, a domain-event type, or an outbox delivery path. Keep source-level dependency direction distinct from runtime calls, and keep entry occurrence dates distinct from domain-event recording timestamps.

Notation references: [C4 model](https://c4model.com/diagrams), [Mermaid flowcharts](https://mermaid.js.org/syntax/flowchart.html), [Mermaid class diagrams](https://mermaid.js.org/syntax/classDiagram.html), and [Mermaid sequence diagrams](https://mermaid.js.org/syntax/sequenceDiagram.html).
