# DDD — a place for your days

A personal journal for meaningful daily events, accomplishments, and achievements. Capture what happened and why it mattered, then revisit your life in a chronological timeline.

- Date entries to the day they happened, including past days.
- Categorize moments as events, accomplishments, or achievements.
- Search titles, stories, and reflections; filter by type and month.
- Read, edit, and delete your own entries. Deletion asks for confirmation.
- See how many moments and distinct days you have recorded.

Entries are scoped to their owner's authenticated, verified account. They are stored in the application database; deleting an account also deletes its journal entries.

Initialized from the official [Laravel Vue starter kit](https://github.com/laravel/vue-starter-kit) at commit `896f0d0`.

The application uses Laravel 13, Vue 3, Inertia 3, TypeScript, Tailwind CSS, and shadcn-vue. Authentication includes registration, email verification, password resets, two-factor authentication, passkeys, and password confirmation.

## Domain-driven design

Journaling is implemented as a bounded context under `app/Journal`. Its framework-independent `Entry` aggregate and value objects enforce the business rules. Write use cases are explicitly named `RecordEntryCommand`, `ReviseEntryCommand`, and `ForgetEntryCommand`; read use cases are `FindEntryQuery` and `ReadJournalQuery`. Eloquent persistence and Laravel's clock implement interfaces owned by the inner layers. HTTP controllers translate requests and responses.

See [the architecture guide](docs/domain-driven-design.md) for the boundaries, dependency diagram, business rules, and test commands. Authentication remains in Fortify, with the journal referring to its owner by identity.

Explore the [C4 architecture diagrams](docs/architecture.md) for system context, containers, backend components, recording contracts, and the transactional outbox sequence, all written in Mermaid.

New entries and their `EventRecordedDomainEvent` are saved atomically in a database outbox. Events remain pending for future publishing. See [the outbox contract](docs/outbox.md) for the versioned JSON format and retention behavior.

All journal write commands use an explicit [Unit of Work](docs/unit-of-work.md) to stage aggregate changes and events, flush them in one transaction, and clear pending work after success or failure.

Reusable [domain abstractions](docs/domain-abstractions.md) define readonly value objects, entity identity, and aggregate roots. The journal model extends these abstractions while keeping its validation and persistence contracts explicit.

## Requirements

- PHP 8.4+ with SQLite support and Composer 2 (for the locked dependencies)
- Node.js 22.18+ (22.x) or 24.11+ (24.x), and npm

## Local setup

```sh
composer setup
composer dev
```

`composer setup` installs dependencies, creates `.env` if needed, generates an application key, runs database migrations, and builds frontend assets. Run it for initial setup; it regenerates the application key on each invocation.

Open [http://localhost:8000](http://localhost:8000). The development command runs the application server, queue worker, logs, and frontend development server together.

SQLite is configured by default at `database/database.sqlite`. Local mail, including verification and password reset messages, is written to `storage/logs/laravel.log`.

Register an account, then follow the verification link in the local mail log to open your journal. Use **Capture a moment** to add your first entry. No example entries or accounts are inserted into your journal.

Set `APP_TIMEZONE` in `.env` to your timezone; it determines today's date for new entries and prevents future-dated entries. The example configuration uses `America/Sao_Paulo`.

Instrument Sans is bundled from an npm package, so development and production builds do not require access to an external font service.

## Xdebug

If PHP reports that it cannot connect to a debugging client on port 9003, use `php --ini` to locate the loaded Xdebug configuration and set:

```ini
xdebug.mode = debug
xdebug.start_with_request = trigger
```

This keeps step debugging available without attempting a connection on every command. Restart any running PHP development processes after changing the configuration.

To debug a command, first start your IDE's Xdebug listener on port 9003, then run:

```sh
XDEBUG_TRIGGER=1 php artisan test
```

For a single command with Xdebug disabled, use `XDEBUG_MODE=off php artisan test`. Adding this variable to Laravel's `.env` does not configure Xdebug; PHP reads its debugging settings before Laravel loads.

## Checks

```sh
composer ci:check
npm run build
```

These commands run read-only frontend lint, TypeScript checks, PHP static analysis, the application test suite, and a production asset build.

## Formatting policy

Preserve manually written formatting. Workspace settings disable formatting on save, paste, and typing, source actions on save, and whitespace cleanup. Formatting checks and automatic fix scripts are excluded from the normal test and CI commands. Project instructions in `AGENTS.md` require an explicit request before running a formatter or automatic fixes.

See the [Laravel starter kit documentation](https://laravel.com/docs/starter-kits) for customization.
