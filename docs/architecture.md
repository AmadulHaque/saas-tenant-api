# Architecture

## Overview

A single Laravel application exposing a versioned JSON API (`/api/v1`). Request flow:

```
HTTP (Octane/FrankenPHP)
  → middleware (throttle, EnsureJsonApiRequest, auth:api)
  → routes/api.php → routes/v1/*.php
  → Form Request validation
  → Controller (authorization via Policies)
  → Service (business rules, transactions, locks)
  → Eloquent models (PostgreSQL)
```

## Layers

### Routes
- `routes/api.php` — applies `throttle:api` to the whole `/v1` group, splits per-resource files under `routes/v1/`.
- Auth routes (`auth.php`) use the `auth` limiter (5/min). Everything else requires `auth:api` + `authenticated` limiter (60/min).
- No implicit model binding: controllers resolve IDs explicitly with tenant-scoped queries (`whereNumber('id')`), so an ID belonging to another tenant is indistinguishable from a nonexistent one (`404`).

### Form Requests (`App\Http\Requests`)
Declarative validation, one class per endpoint flavor. List requests own pagination/filter rules (`per_page` max 100, `search`, `role`, `status`). Mass-assignment is impossible: models declare `$fillable` explicitly, and role/company fields are set via `forceFill` in controllers after authorization.

### Controllers (`App\Http\Controllers\Api\V1`)
Single Responsibility Principle applied at the route level: **one invokable controller class per endpoint**, grouped by resource (`Auth\RegisterController`, `User\IndexController`, `User\StoreController`, …). Each class does one thing: authorize (`$this->authorize()`), delegate to a service, transform via an API Resource. Shared concerns live in `App\Http\Controllers\Concerns` (`ResolvesTenantCompany`, `AuthorizesSubscription`); tenant-scoped lookups share the `forCompany` model scope. Conventions: JSON responses shaped `{ "<resource>": … }`, `201` on create, `404` scoped-not-found, `403` role failures, `422` validation/limit errors. Every route is a standalone registration in `routes/v1/*.php` pointing at its own class — no `[Controller::class, 'method']` arrays.

### Services (`App\Services`)
| Service | Responsibility |
| --- | --- |
| `TenantRegistrationService` | Atomic registration: user + company + owner link in one transaction, unique slug generation with random suffix |
| `SubscriptionService` | Subscribe/change plan (row lock on company), cancel, lazy expiration, global sweep, feature-limit resolution |
| `SubscriptionLimitService` | Count-and-create under a transaction + `lockForUpdate` on the company row; breach → `ValidationException` (422) |
| `DashboardService` | Cached analytics assembly |

Critical synchronous logic (authorization, limit enforcement) never leaves this layer — nothing async guards writes.

### Models & enums
- Enums: `UserRole`, `SubscriptionStatus`, `BillingInterval`, `CustomerStatus` (PHP backed enums; `BillingInterval::months()` drives `ends_at`).
- `SubscriptionPlan::limit()` reads the JSONB `limits` payload (`null` = unlimited).
- `Subscription::scopeActive` / `scopePastDue` centralize status windows.

### Authorization (`App\Policies`)
`CompanyPolicy`, `UserPolicy`, `CustomerPolicy`, `SubscriptionPolicy` + shared `ChecksTenant` concern:

| Resource | View | Create/Update | Delete |
| --- | --- | --- | --- |
| Company | all members | owner | — |
| Users | owner/admin | owner/admin | owner/admin (never owner rows, never self) |
| Customers | all members | owner/admin | owner/admin |
| Subscription | owner | owner | owner (cancel = update) |

### Observers (`App\Observers`)
`FlushTenantDashboardCache` (User, Customer, Subscription) and `FlushPlansCache` (SubscriptionPlan) keep caches tied to the actual data flow. Registered in `AppServiceProvider`.

## Background processing
- **Scheduler** (`routes/console.php`): `subscriptions:expire` hourly with `withoutOverlapping` + `onOneServer`. Idempotent bulk status flip; flushes affected tenants' caches explicitly (bulk updates skip Eloquent events).
- **Horizon** monitors the Redis queue. No queued jobs exist because nothing in the domain genuinely benefits from async processing (see `docs/decisions.md`).
- **Lazy expiration** guarantees correctness even if the scheduler is down: any read of a tenant's subscription first expires past-due rows.

## Octane notes
The app runs under Octane/FrankenPHP (long-lived workers). Rules followed:
- No request-scoped state in singletons/statics; services are stateless and resolved per use.
- Rate limiter keys and cache use Redis/array stores, not process memory.

## API documentation
Scramble auto-generates the OpenAPI document from code (`/docs/api.json`). `docs/api.md` is the human reference.

## Testing
Pest feature tests cover auth, CRUD, policies, tenant isolation (IDOR), subscriptions, limits, caching, scheduling, rate limiting. Runs on SQLite `:memory:` for speed; pgsql-only constraints are guarded and verified separately.
