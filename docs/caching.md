# Caching Strategy

Store: **Redis** (`CACHE_STORE=redis`), accessed through Laravel's cache with **tags** (supported by `redis`/`array`, required — the app refuses no other assumption). Rate limiter counters and the queue share the Redis instance but use separate keys.

Two resources are cached. Nothing else is cached deliberately — tenant CRUD reads are cheap, filtered, and paginated; caching them would trade correctness for nothing.

## 1. Tenant dashboard

| Aspect | Value |
| --- | --- |
| Key | `tenant:{companyId}:dashboard` |
| Tags | `["dashboard", "tenant:{companyId}"]` |
| TTL | **300 seconds** (5 minutes) |
| Data | `total_users`, `total_customers`, active subscription summary (status, plan name, interval, ends_at), 5 most recent users, 5 most recent customers, `generated_at` |
| Tenant scope | `companyId` is taken from the authenticated principal's company — never from request input. Tag `tenant:{companyId}` scopes invalidation per tenant. |
| Written by | `DashboardService::for()` |

**Invalidation triggers** (all wired through observers to the actual data flow):

- `User` saved / deleted / force-deleted → flush `tenant:{companyId}` (`FlushTenantDashboardCache`)
- `Customer` saved / deleted / force-deleted → same
- `Subscription` saved → same (covers subscribe / change plan / cancel via `SubscriptionService`, and direct writes)
- `UsageRecord` created → same (usage totals live on the dashboard; the append-only ledger never bulk-updates)
- `SubscriptionService::expireAllPastDue()` bulk-updates rows (bypassing Eloquent events), so it flushes affected tenants **explicitly** after the update
- TTL as the safety net

**Stampede:** plain `Cache::remember` — on expiry, concurrent requests may each recompute. Accepted: the payload is two counts + two `LIMIT 5` queries (~ms), a stampede is harmless, and `remember` keeps the code honest. Not worth a lock round-trip.

**Isolation guarantees:**

- Keys embed the tenant id; the `tenant:{id}` tag means tenant A's writes can never evict tenant B's entry (verified by tests).
- A user of tenant A can only ever read the cache entry computed for company A because the key is derived server-side from their token.

## 2. Plan catalog

| Aspect | Value |
| --- | --- |
| Key | `plans:all` |
| Tags | `["plans"]` |
| TTL | **3600 seconds** (1 hour) |
| Data | Collection of active plans (id, name, slug, price, interval, limits) |
| Tenant scope | None — plans are global, non-sensitive catalog data; sharing the entry across tenants is intentional |
| Written by | `PlanController::index()` |

**Invalidation:** `FlushPlansCache` on `SubscriptionPlan` saved/deleted; TTL as safety net. Any plan change therefore becomes visible on the next request.

## TTL rationale

- Dashboard: 5 minutes — analytics freshness users expect from a dashboard; short enough that even without invalidation the worst case is 5-minute-stale counts.
- Plans: 1 hour — near-immutable catalog; observer invalidation makes the TTL purely defensive.

## Redis-unavailable fallback

- `DashboardService` wraps the cache read in `try/catch (Throwable)`: if Redis is down, the dashboard is **computed fresh from the database** and returned normally (HTTP still `200`). The outage is transparent to clients.
- Observers swallow cache failures the same way: a Redis outage must not turn a successful `POST /users` into a `500`.
- Rate limiting degrades to failing open/closed per Laravel's throttle behavior; the queue backend surfaces the outage via Horizon.
- Trade-off: during an outage, correctness is preserved, latency gains are lost.

## Testing

`tests/Feature/Dashboard/DashboardTest.php` covers: cache **hit** (a `createQuietly` write stays invisible), **miss/invalidation** (API writes flush immediately), **key isolation** (entries are per-tenant), **cross-tenant non-eviction**, Redis-down **fallback** (mocked store throws, response still fresh), and plan-cache refresh after plan writes.

`tests/Feature/Subscriptions/ExpirationSweepTest.php` asserts the sweep evicts exactly the affected tenant's entry.
