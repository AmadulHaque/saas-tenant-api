# Technical Decisions

Rationale for the choices a reviewer might question.

## Tenancy

**D1 — Shared database, shared schema, `company_id` discriminator.**
Chosen over schema-per-tenant / database-per-tenant: single migration path, connection pooling works, Horizon/queue workers stay simple. The cost — isolation depends on application discipline — is mitigated by three layers: (1) every read/write goes through tenant-scoped queries, (2) policies re-check tenant ownership, (3) the one invariant that must *never* break (one active subscription per company) is enforced by a partial unique index in PostgreSQL, not just code.

**D2 — Cross-tenant IDs return `404`, not `403`.**
`GET /api/v1/users/{id}` for a foreign id is indistinguishable from a nonexistent id. A `403` would confirm the resource exists — a small but real enumeration leak. Role failures on resources you legitimately can see remain `403`. No implicit route binding anywhere; controllers resolve IDs through tenant-scoped queries.

**D3 — One user ↔ one company, global-unique email.**
Invitations and multi-company membership are out of scope. Since `email` is the login identifier, a single global unique constraint prevents duplicate identities and doubles as the tenant boundary in `auth`. Owner deletion nulls `companies.owner_id` (company survives, destructive policy checks then fail closed).

**D4 — Single `role` column with a PHP enum, no role table.**
Three fixed roles (`owner|admin|member`) need no runtime flexibility. Policies centralize the permission matrix; a roles/permissions pivot would be speculative machinery.

## Subscriptions & limits

**D5 — Partial unique index for "one active subscription per company".**
`UNIQUE (company_id) WHERE status = 'active'` keeps full subscription history in-table (plan changes cancel the previous row) while making double-activation impossible at the storage layer — verified by tests that hit the raw constraint (23505).

**D6 — `ends_at > starts_at` as a database check constraint.**
Bad period data would silently corrupt expiration logic. The constraint is PostgreSQL-only; the SQLite test build omits it and the dedicated test skips rather than fakes a pass. Verified live (23514).

**D7 — Limits: `null` means unlimited; no active subscription means unlimited.**
Enforcement activates only when a tenant has a live subscription reading an explicit limit from the plan's JSONB payload. This makes "no payment method yet" onboarding friction-free and keeps the demo tenant usable. Trade-off accepted and documented: removing a subscription lifts limits — billing integration would close this.

**D8 — Limit enforcement is synchronous and row-locked.**
`SubscriptionLimitService` counts and creates inside one transaction holding `lockForUpdate` on the company row, so concurrent creations serialize and the count cannot be stale. Deliberately **not** moved to a queue: an async check is a race condition with a nicer name.

**D9 — Lazy + scheduled expiration.**
Every subscription read first expires past-due rows (correctness independent of cron), and `subscriptions:expire` runs hourly (`withoutOverlapping`, `onOneServer`) to make state deterministic for sweeps/notifications. The bulk update skips Eloquent events, so the service flushes affected tenants' dashboard caches explicitly — invalidation never depends on events that bulk writes don't fire.

## Caching

**D10 — Only two cached resources.** Plans (global, read-heavy, rarely changes) and per-tenant dashboards (computed from 4+ queries on every request). CRUD lists stay uncached: filtered + paginated reads are cheap and correctness-sensitive.

**D11 — Tag-based invalidation keyed on the data flow.** `tenant:{companyId}` tags give per-tenant eviction precision; `plans` tags give instant catalog refresh. Observers mean "write path ⇒ invalidation" structurally — no TTL-only stale windows, no disconnected cron invalidation. See `docs/caching.md`.

**D12 — No stampede locks.** `Cache::remember` without locks; both payloads cost milliseconds to recompute, so a stampede is cheaper than lock infrastructure.

## Platform

**D13 — Passport personal access tokens only.** Password/OAuth grants are disabled by default. PATs are the right primitive for this API's audience; 30-day expiry, revocation on logout and on user deletion.

**D14 — Integer cents (`price_cents`) and enum billing intervals.** No floats for money; `BillingInterval::months()` is the single place that maps intervals to `ends_at` arithmetic.

**D15 — SQLite `:memory:` for the test suite, with an honest pgsql escape hatch.** Speed and parallelism; the two PostgreSQL-only constraints are guarded so the suite stays green everywhere, and they are additionally verified live. No test pretends SQLite enforced them.

**D16 — Octane/FrankenPHP.** Long-lived workers for throughput; the codebase holds no request state in singletons, and limiter/cache state lives in Redis. Horizon ships for queue observability; Telescope for local debugging.

**D17 — No artificial queued jobs.** The brief allows skipping async work when nothing genuinely benefits. Nothing here does: expiration is a scheduled command, limit enforcement must be synchronous (D8), and there are no emails/exports yet. The scheduler + Horizon infrastructure is in place so a real async need (notifications, aggregation) can be added without re-plumbing.

**D18 — Usage is an append-only ledger with signed deltas.** `POST /usage` records `{feature, delta, metadata}`; corrections are negative deltas, never updates. Totals are a `SUM` over `(company_id, feature)`. Usage is not part of the dashboard payload, so — per the doc's own rule — no cache invalidation is wired for it (there is nothing cached to invalidate). Enforcing limits against usage totals is deliberately left to plan limits (`max_users`/`max_customers`), which are count-based.

**D19 — Redis client auto-selection (`phpredis` → `predis`).** Telescope's `Telescope::start()` reads the default cache store during provider boot on every artisan invocation. With `REDIS_CLIENT=phpredis` hard-coded, any machine whose PHP lacks the phpredis extension crashed at boot (`Class "Redis" not found`) — common for reviewers running stock PHP. The client now defaults to `phpredis` when the extension is loaded and falls back to `predis` (pure PHP) otherwise; `REDIS_CLIENT` still overrides. Trade-off: predis is slower than the extension, but the application stays runnable everywhere and production keeps the extension.

**D20 — Defense-in-depth headers are config-driven and wired, not decorative.** `config/security.php` feeds real enforcement: `SecurityHeaders` (global) optionally 301-redirects HTTP→HTTPS (`SECURITY_FORCE_HTTPS`), emits HSTS only on secure/forced connections, stamps `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` everywhere, and applies `Content-Security-Policy: default-src 'none'` to API responses only so the Scramble docs UI keeps its scripts. `EnsureTrustedHost` rejects spoofed `Host` headers when `TRUSTED_HOSTS` is set (empty = disabled locally). Wiring these surfaced a real trap: blank `.env` entries (`KEY=`) resolve to empty strings, not null, so `env('KEY', default)` defaults never applied (`SECURITY_HSTS_MAX_AGE=` yielded `max-age=0`). The config now falls back to defaults for null *and* blank values.

**D21 — OWASP posture for a stateless token API.** Supply chain: `composer audit --locked` runs inside `composer ci`; exact versions are pinned by the committed lock file. Sessions/cookies: not applicable — the API is stateless Passport personal-access-token auth (30-day expiry, revocation on logout and user deletion); each login mints a fresh token, so there are no session cookies to flag `HttpOnly`/`SameSite`. MFA is deliberately out of scope: the assignment's auth surface is token-per-user and adding TOTP would expand scope without a requirement; `auth` rate limiting (5/min per IP+email) already throttles credential stuffing. Injection: no raw SQL anywhere (Eloquent parameterized, grep-verified); XSS surface is nil (JSON-only responses, escaped Blade error pages). Error detail: generic 500s in production via `APP_DEBUG=false`, JSON exception rendering scoped to `api/*`.

**D22 — Billing is gateway-agnostic: invoices own the truth, subscriptions are the outcome.** The assignment made payments optional, so there is no real gateway integration to verify against — instead `BillingGateway` is a one-method contract with a config-driven `FakeGateway` (`paid|pending|failed`), and a real driver (Stripe) slots in behind `BILLING_GATEWAY` without touching domain code. `POST /billing/checkout` charges inside the same company row lock as subscription changes: instant success activates via `SubscriptionService` (so one-active-subscription invariants and cache invalidation are reused, never duplicated), `pending` returns `202` and waits for the HMAC-signed `POST /webhooks/billing` callback (idempotent, lock-re-checked, safe for duplicate deliveries), declines leave a `failed` invoice and nothing else. Invoices snapshot the plan name and amount; they are the audit trail, subscriptions the live state. Not built, deliberately: recurring renewals/dunning (needs a scheduler-driven charging cycle — D17 territory once real money flows) and stored payment methods.

## Known debt / upstream breakage

- The scaffold's `composer types` script (phpstan with no paths, no `phpstan.neon`) failed as shipped upstream; it has been fixed to analyse `app tests database/factories database/seeders bootstrap lang`.
- The Docker Compose stack was authored but not build-verified in the development environment (daemon unavailable); it is intentionally conservative (official images, healthchecks, entrypoint waits for Postgres).
