# Verita API

[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](https://www.php.net)

Backend API for Verita, an anonymous workplace-reporting and case-management system. It provides JWT-authenticated staff workflows, anonymous case tracking, AI-assisted case structuring, private evidence, real-time messaging, notifications, and manager reporting.

**Repository:** [CarmineAkanabe/verita-api](https://github.com/CarmineAkanabe/verita-api)

## What is here

- Anonymous case submission with a one-time tracking PIN
- Separate JWT access for case reporters and staff (`DEPARTMENT_HEAD`, `MANAGER`)
- Gemini-powered case structuring, processed asynchronously
- Private evidence storage and role-aware access
- Case claiming, assignment, status updates, escalation, and audit logs
- Real-time reporter/Department Head chat through Laravel Reverb
- In-app/email notifications and manager engagement analytics

## Documentation

| Document | Purpose |
| --- | --- |
| [API-DOCUMENTATION.md](API-DOCUMENTATION.md) | Consumer contract for Vue and Bruno: endpoints, payloads, auth, errors, Reverb, and Bruno guidance |
| [PROGRESS.md](PROGRESS.md) | Phase history and implementation-audit corrections |

The API documentation is the source of truth for consumers; it is derived from the currently implemented routes and behavior.

## Prerequisites

- PHP 8.3 or later and Composer
- PostgreSQL
- Redis, using the Predis client
- Node.js/npm if building the bundled Echo frontend helper
- A Gemini API key for live AI case structuring

SMTP and Reverb are optional for basic API development, but required for the full notification/chat flow.

## Quick start

```bash
git clone https://github.com/CarmineAkanabe/verita-api.git
cd verita-api
composer install
copy .env.example .env       # Windows PowerShell/CMD
# cp .env.example .env       # macOS/Linux
php artisan key:generate
php artisan jwt:secret
```

Configure `.env`, then run migrations and seed the repeatable demo dataset:

```bash
php artisan migrate
php artisan demo:reset --force
```

Start the services in separate terminals:

```bash
php artisan serve
php artisan queue:work
php artisan reverb:start
```

The API health check is available at `GET /api/v1/ping`.

## Environment configuration

The active local configuration uses PostgreSQL and Redis. These values belong in `.env`; never commit credentials.

| Area | Required settings |
| --- | --- |
| Application | `APP_URL`, `APP_KEY`, `JWT_SECRET` |
| PostgreSQL | `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| Redis | `REDIS_CLIENT=predis`, `REDIS_HOST`, `REDIS_PORT`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis` |
| Gemini | `GEMINI_API_KEY`; optional `GEMINI_MODEL` (defaults to `gemini-3.5-flash`) |
| Reverb | `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`, plus matching `VITE_REVERB_*` settings |
| Mail | `MAIL_MAILER=log` locally, or SMTP settings for real mirrored notifications |

Run `php artisan config:clear` after changing environment values if configuration was cached.

## API at a glance

All application endpoints use the `/api/v1` prefix. Send `Accept: application/json` and use Bearer tokens after authentication.

- Staff login: `POST /api/v1/auth/login`
- Case submission: `POST /api/v1/cases` (multipart, requires `Idempotency-Key`)
- Case PIN verification: `POST /api/v1/cases/{caseId}/verify-pin`
- Case reporter dashboard: `GET /api/v1/cases/me`
- Staff case queue: `GET /api/v1/cases`
- Manager reporting: `GET /api/v1/reports/user-engagement`

Current IP-based limits are 50 login attempts/minute, 30 case submissions/minute, and 10 PIN-verification attempts/minute. See [API-DOCUMENTATION.md](API-DOCUMENTATION.md) for the complete contract, including expected error envelopes and response shapes.

## Demo workflow

`php artisan demo:reset --force` truncates and reseeds the demo data without calling Gemini. Run it before a rehearsal to restore known data. For a complete demo, keep the app server, queue worker, and Reverb server running: queued AI work and notifications do not execute without the worker, and chat requires Reverb.

For SMTP demos, Gmail plus-addressing can route apparent Department Head and Manager recipients to a single inbox, for example `yourname+manager@gmail.com`.

## Testing

Tests deliberately use the local, gitignored `.env.testing` file. The environment
entries in `phpunit.xml` are commented out, so PHPUnit/Pest does not override the
PostgreSQL, Redis, mail, queue, or JWT configuration defined there.

Create your local testing environment once from the committed template, fill in the
PostgreSQL credentials, then generate test-only secrets:

```bash
copy .env.testing.example .env.testing       # Windows PowerShell/CMD
# cp .env.testing.example .env.testing       # macOS/Linux
php artisan key:generate --env=testing
php artisan jwt:secret --env=testing
php artisan migrate --env=testing
```

`.env.testing` uses the separate `verita_testing` database, Redis cache database `1`,
an array mailer, and synchronous queues. Do not point it at the development database.

Run the suite with:

```bash
php artisan test --compact
# or
./vendor/bin/pest
```

If the test schema needs to be rebuilt, use `php artisan migrate:fresh --env=testing`
only after confirming that `DB_DATABASE` is the disposable test database.

## External consumers

The Vue application should use [API-DOCUMENTATION.md](API-DOCUMENTATION.md) for REST and Reverb integration. The Bruno collection should use workspace environments, collection/folder scripts for token chaining, and CLI reports in CI; the recommended variable and script workflow is documented there as well.

## License

This project is part of the Verita final-defence work. Add an explicit license before publishing it for reuse.
