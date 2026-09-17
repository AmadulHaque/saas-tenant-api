# SaaS Tenant API

A production-style SaaS Subscription & Tenant Management REST API built with **Laravel 13**, **PHP 8.4**, **PostgreSQL**, **Redis**, **Passport**, and **Octane (FrankenPHP)**.

Companies (tenants) register, manage users and customers, subscribe to plans with per-feature limits, and read analytics from a cached dashboard. Data isolation is enforced at the query and policy layers.

## Features

- **Authentication** — Laravel Passport personal access tokens (30-day lifetime). Register, login, logout (token revocation), `/me`.
- **Authorization** — role column (`owner`, `admin`, `member`) with Laravel Policies. Subscription management is owner-only.
- **Multi-tenancy** — shared database with `company_id` scoping. Cross-tenant IDs resolve to `404`; insufficient role returns `403`.
- **Subscription plans** — public-to-authenticated plan catalog (monthly/yearly pricing, JSONB feature limits).
- **Subscriptions** — subscribe, change plan (old row cancelled, history kept), cancel, automatic expiration (lazy + scheduled hourly sweep). One active subscription per tenant is enforced by a **partial unique index** in the database, not just application logic.
- **Feature limits** — `max_users` / `max_customers` enforced on creation under a row lock so concurrent requests cannot exceed a plan.
- **Dashboard analytics** — per-tenant counts, subscription summary, recent activity.
- **Usage tracking** — append-only usage ledger (`POST/GET /usage`) with signed deltas per feature.
- **Redis caching** — per-tenant dashboard cache and shared plan cache with observer-driven invalidation and a Redis-unavailable fallback (see `docs/caching.md`).
- **Background jobs** — scheduled `subscriptions:expire` command (hourly, `withoutOverlapping`, `onOneServer`). No artificial queued jobs — see `docs/decisions.md`.
- **Rate limiting** — 5/min on auth endpoints (IP + email), 60/min global API, 60/min on authenticated routes.
- **API documentation** — auto-generated OpenAPI via [Scramble](https://scramble.dedoc.com/) at `/docs/api`.
- **Tooling** — Pest tests, PHPStan (level max), Laravel Pint, Horizon, Telescope.

## Requirements

- PHP 8.4+ with extensions: `pdo_pgsql`, `redis` (phpredis), `pcntl`, `mbstring`, `openssl`
- Composer 2
- PostgreSQL 16+
- Redis 7+
- Docker + Docker Compose (optional, for containerized setup)

## Installation

```bash
git clone <repository-url> saas-tenant-api
cd saas-tenant-api
composer install
cp .env.example .env
php artisan key:generate
```

## Environment setup

`.env.example` ships with safe placeholders and is the source of truth for required variables:

```ini
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=saas-tenant-db
DB_USERNAME=...
DB_PASSWORD=...

CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

For local development against a plain Laravel server, `CACHE_STORE` may be set to any tag-capable store (`redis`, `array`). Tags are required by the caching layer.

## Database setup

```bash
php artisan migrate              # create schema
php artisan db:seed              # plans, demo tenant, Passport PAT client
```

Or reset everything at once:

```bash
php artisan migrate:fresh --seed
```

Seeders create:

- Plans: **Free** ($0, 3 users, 50 customers), **Starter** ($29/mo, 10 users, 500 customers), **Pro** ($99/mo, 50 users, 5000 customers)
- Demo tenant **Acme Ltd** (`acme`) with owner, admin, and member accounts
- The Passport **Personal Access Client**

## Authentication instructions

All API routes require `Accept: application/json`. Personal access tokens are sent as bearer tokens:

```bash
TOKEN=$(curl -s -X POST http://localhost:8000/api/v1/auth/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"owner@acme.test","password":"password"}' | jq -r .token)

curl -s http://localhost:8000/api/v1/me -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

Tokens are created at registration/login and revoked on logout. Lifetime is 30 days (`Passport::personalAccessTokensExpireIn`).

## API usage

Base URL: `/api/v1`. Full reference: `docs/api.md` or the live OpenAPI document at `/docs/api.json`.

```bash
# Create a user (owner/admin only, counts toward max_users)
curl -s -X POST http://localhost:8000/api/v1/users \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Jane","email":"jane@acme.test","password":"secret123","role":"member"}'

# Subscribe to the Pro plan
curl -s -X POST http://localhost:8000/api/v1/subscription \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"plan_id":3}'

# Cached dashboard analytics
curl -s http://localhost:8000/api/v1/dashboard \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

Validation errors return `422` with a `{ "message", "errors" }` payload; authorization failures return `403`; resources belonging to another tenant return `404`.

## Test commands

```bash
composer test                                     # full suite (parallel)
php artisan test --compact                       # sequential
vendor/bin/pest tests/Feature/Subscriptions       # single directory
vendor/bin/pint --dirty                          # code style
vendor/bin/phpstan analyse app tests bootstrap --memory-limit=-1   # static analysis
```

The suite runs on SQLite `:memory:` by default (see `phpunit.xml`). PostgreSQL-only constraints (partial unique index, period check) are additionally verified by a dedicated test that skips on SQLite.

## Docker commands

```bash
docker compose up --build -d     # app (Octane), postgres, redis, horizon, scheduler
docker compose logs -f app       # watch the API
docker compose down -v           # tear down (drops data)
```

The app container migrates on boot and seeds demo data on first start (`SEED_DEMO=true`). Swagger/OpenAPI is exposed at `http://localhost:8000/docs/api.json`. The compose file is untested against a live daemon in this environment; report issues with `docker compose logs`.

## Redis setup

Redis is required for cache, rate limiter counters, and the queue backend. Start it locally with:

```bash
redis-server
```

`CACHE_STORE=redis` and `REDIS_HOST`/`REDIS_PORT` must point at the instance. The API degrades gracefully if Redis goes down mid-flight (fresh computes instead of cached reads) — see `docs/caching.md`.

## Queue setup

Queues use the `redis` connection and are monitored by **Horizon**:

```bash
php artisan horizon                      # queue dashboard on /horizon
php artisan schedule:work                # local scheduler (subscriptions:expire, hourly)
```

In production, run the scheduler via cron: `* * * * * php /path/artisan schedule:run`.

## Demo credentials

Password for all demo accounts: `password`

| Email | Role |
| --- | --- |
| `owner@acme.test` | owner |
| `admin@acme.test` | admin |
| `member@acme.test` | member |

## Architecture summary

- **Routes** (`routes/api.php`, versioned `/api/v1`) → **Form Requests** (validation) → **Controllers** (`App\Http\Controllers\Api\V1`) → **Services** (`App\Services`) → **Eloquent models**.
- **Services**: `TenantRegistrationService`, `SubscriptionService`, `SubscriptionLimitService`, `DashboardService`.
- **Authorization**: `App\Policies` + `ChecksTenant` concern; role + tenant checks in one place.
- **Cache invalidation**: `App\Observers` flush tenant dashboards and plan cache on writes.
- **Expiration**: lazy check on every subscription read + hourly global sweep.
- Details: `docs/architecture.md`, `docs/database.md`, `docs/caching.md`, `docs/decisions.md`.

## Known limitations

- One user belongs to exactly one company (no invites/multi-membership).
- Soft-deleted records are excluded from counts immediately; hard-deletion is not exposed via the API.
- Payment/billing is out of scope: `price_cents` and billing intervals exist but no invoices.
- Plan limits are read from JSONB; changing a plan's limits mid-flight affects existing subscribers immediately.
- Usage is recorded but not yet surfaced on the dashboard or enforced against plan limits.
- The PostgreSQL-only constraints are skipped (not faked) in the SQLite test run; they are exercised live and in `tests/Feature/SchemaTest` on pgsql.

## Trade-offs

- **Shared-schema tenancy** with `company_id` scoping was chosen over database-per-tenant for operational simplicity; isolation is enforced in repositories/policies (see `docs/decisions.md`).
- **Null limits mean unlimited**, and tenants without an active subscription are not limited — documented deliberately in `docs/decisions.md`.
- **Cache stampede**: plain `Cache::remember` (no lock) for dashboard/plans; the payloads are cheap to recompute. Rationale in `docs/caching.md`.
