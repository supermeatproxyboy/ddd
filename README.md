# Laravel + Vue Application

Initialized from the official [Laravel Vue starter kit](https://github.com/laravel/vue-starter-kit) at commit `896f0d0`.

The application uses Laravel 13, Vue 3, Inertia 3, TypeScript, Tailwind CSS, and shadcn-vue. Authentication includes registration, email verification, password resets, two-factor authentication, passkeys, and password confirmation.

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

Instrument Sans is bundled from an npm package, so development and production builds do not require access to an external font service.

## Checks

```sh
composer ci:check
npm run build
```

These commands run frontend formatting and lint checks, TypeScript checks, PHP formatting and static analysis, the application test suite, and a production asset build.

See the [Laravel starter kit documentation](https://laravel.com/docs/starter-kits) for customization.
