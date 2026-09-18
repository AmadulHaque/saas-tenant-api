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
- **API documentation** — auto-generated OpenAPI via [Scramble](https://scramble.dedoc.com/) at `/docs/api`. A ready-to-import Postman collection lives at `docs/postman_collection.json` (set `base_url` and `token` variables).
- **Tooling** — Pest tests, PHPStan (level max), Laravel Pint, Horizon, Telescope.

## Requirements

- PHP 8.4+ with extensions: `pdo_pgsql`, `redis` (phpredis — optional; `predis` is used automatically when the extension is absent), `pcntl`, `mbstring`, `openssl`
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

## Security

- **Transport**: set `SECURITY_FORCE_HTTPS=true` in production to 301-redirect plain HTTP and force `https://` in generated URLs; HSTS (`Strict-Transport-Security`) is emitted on secure connections, tunable via `SECURITY_HSTS_*`.
- **Headers**: every response carries `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`; API responses additionally get `Content-Security-Policy: default-src 'none'; frame-ancestors 'none'`.
- **Host allow-list**: set `TRUSTED_HOSTS=api.example.com,api2.example.com` to reject spoofed `Host` headers (empty = disabled, local default).
- **Supply chain**: `composer audit --locked` is part of `composer ci`; exact versions are pinned in the committed lock file.
- **Auth**: stateless Passport personal access tokens (30-day expiry, revoked on logout and user deletion); `auth` routes are rate limited at 5/min per IP+email. MFA is not implemented (documented in `docs/decisions.md` D21).
- **Data**: passwords are bcrypt-hashed; there is no raw SQL (Eloquent parameterized queries only); production errors are generic (`APP_DEBUG=false`).

## Billing

Plan purchase is implemented behind a gateway-agnostic contract (`App\Services\Billing\BillingGateway`). The bundled `fake` driver is config-driven, so every path is exercisable without external services:

| Env | Effect |
| --- | --- |
| `BILLING_GATEWAY=fake` | Active driver (bind a real one in `AppServiceProvider` for Stripe etc.) |
| `BILLING_FAKE_BEHAVIOR=paid` | Checkout settles instantly → `201` + active subscription |
| `BILLING_FAKE_BEHAVIOR=pending` | Checkout returns `202`; settle via the signed webhook (see below) |
| `BILLING_FAKE_BEHAVIOR=failed` | Checkout returns `402`; invoice records the decline |
| `BILLING_WEBHOOK_SECRET=…` | Enables `POST /api/v1/webhooks/billing` (HMAC-SHA256 of the raw body in `X-Signature`); unset → webhook answers `503` |

```bash
# Simulate an async gateway confirmation for invoice 1:
BODY='{"invoice_id":1,"status":"paid","reference":"gw_confirm_1"}'
SIG=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac "$BILLING_WEBHOOK_SECRET" -hex | sed 's/^.* //')
curl -X POST http://localhost:8000/api/v1/webhooks/billing \
  -H "Content-Type: application/json" -H "X-Signature: $SIG" -d "$BODY"
```

Endpoints (owner only): `POST /billing/checkout`, `GET /billing/invoices`, `GET /billing/invoices/{id}` — see `docs/api.md`. Invoices snapshot the plan name/amount at purchase; activation always flows through `SubscriptionService`, so plan-change history, the one-active-subscription rule, and cache invalidation behave identically to direct subscription changes.

## Known limitations

- One user belongs to exactly one company (no invites/multi-membership).
- Soft-deleted records are excluded from counts immediately; hard-deletion is not exposed via the API.
- Payment/billing is out of scope: `price_cents` and billing intervals exist but no invoices.
- Plan limits are read from JSONB; changing a plan's limits mid-flight affects existing subscribers immediately.
- Usage is recorded and surfaced as signed per-feature totals on the dashboard, but not enforced against plan limits (limits are count-based; see `docs/decisions.md` D23).
- Billing has no recurring renewals/dunning or stored payment methods: each checkout is a one-off charge, and invoices are audit records only (see `docs/decisions.md` D22).
- The PostgreSQL-only constraints are skipped (not faked) in the SQLite test run; they are exercised live and in `tests/Feature/SchemaTest` on pgsql.

## Trade-offs

- **Shared-schema tenancy** with `company_id` scoping was chosen over database-per-tenant for operational simplicity; isolation is enforced in repositories/policies (see `docs/decisions.md`).
- **Null limits mean unlimited**, and tenants without an active subscription are not limited — documented deliberately in `docs/decisions.md`.
- **Cache stampede**: plain `Cache::remember` (no lock) for dashboard/plans; the payloads are cheap to recompute. Rationale in `docs/caching.md`.
