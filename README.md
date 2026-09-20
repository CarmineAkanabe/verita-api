# Verita API

Laravel backend for Verita — see `verita-master-spec.md` for the domain
spec and `verita-implementation-plan.md` for build order. This README
only covers running the thing.

## Requirements

- PHP 8.3+, Composer
- PostgreSQL
- Redis (Predis client, not phpredis extension)

## Setup

```bash
git clone <repo>
cd verita-api
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret        # populates JWT_SECRET, needed by tymon/jwt-auth
```

Fill in `.env`:

| Var                                                                                                                                               | Notes                                         |
| ------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------- |
| `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`                                                          | standard                                      |
| `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `REDIS_CLIENT=predis`, `REDIS_HOST`, `REDIS_PORT`                                                  | queue backs AI processing + notification jobs |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME` + matching `VITE_REVERB_*`                  | chat broadcasting                             |
| `MAIL_MAILER=log` (dev) / `smtp` (demo day), `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION=tls` | Gmail SMTP for mirrored notifications         |
| `GEMINI_API_KEY` *(confirm against `config/services.php` — inferred name)*                                                                        | AI structuring                                |

## Database

```bash
php artisan migrate
php artisan demo:reset --force   # truncates + reseeds 5 cases across every status
```

`demo:reset` makes zero Gemini calls — every seeded case's AI fields are
hand-written, so it works offline and instantly. Run it before every
rehearsal to get back to a known-clean state.

## Running it (3 processes, all required)

```bash
php artisan serve          # or your web server of choice
php artisan queue:work     # AI processing + notification listeners — inert without this
php artisan reverb:start   # chat won't connect without this
```

All three need to be up simultaneously for a full demo (submit → AI →
chat → resolve → notify). Missing `queue:work` is the most common
"nothing's happening" cause — jobs just sit queued silently.

## Rate limits (already wired, worth knowing during rehearsal)

- Login: 5/min
- Submit Case: 3/min
- PIN verify: 10/min

Hitting these mid-rehearsal looks like a hang, not an error — if a demo
step stalls, check this before assuming something's broken.

## Simulating multiple email recipients (Gmail alias trick)

Gmail ignores anything after `+` in the local part of an address —
`you+deptheadA@gmail.com` and `you+manager@gmail.com` both land in the
same real inbox as distinct, individually visible/filterable addresses.

For demo day, override the seeded Department Heads' and Manager's
emails (in `DatabaseSeeder`, or manually via tinker/DB update
afterward) to aliases of one real Gmail account you control — e.g.
`yourname+swehead@gmail.com`, `yourname+managers@gmail.com` — and set
`MAIL_MAILER=smtp` with that account's credentials for the session.
Every one of the four mirrored notification triggers then visibly
lands as a distinct, attributable email in one inbox, without needing
several real mailboxes.

## Tests

```bash
./vendor/bin/pest
```
