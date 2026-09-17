# Database

PostgreSQL 16+. All tables are in one shared schema; tenancy is enforced by `company_id` scoping plus database-level constraints where the invariant is absolute.

## Entity overview

```
users ──┐
        ├── companies ──── subscriptions ──── subscription_plans
        └── customers ──── usage_records ────┘ (feature limits live on plans)
```

A company has exactly one *active* subscription at any moment (enforced in the database). Subscription history is preserved: plan changes cancel the previous row, they never update it in place.

## Tables

### companies
| Column | Type | Notes |
| --- | --- | --- |
| id | bigint PK | |
| name | string | |
| slug | string | **unique**; generated from name with random suffix on collision |
| owner_id | FK → users.id, nullable, unique | `null on delete` — company survives owner deletion, policy layer then denies destructive actions |
| timestamps, soft deletes | | |

### users (migration alters the scaffold)
| Column | Type | Notes |
| --- | --- | --- |
| company_id | FK → companies.id, nullable | `null on delete`; index |
| role | enum `owner\|admin\|member`, nullable | single column, no join table |
| soft deletes | | deleting a user also revokes their API tokens |

Email is **globally unique** (login identifier; one user = one company, see `docs/decisions.md`).

### subscription_plans
| Column | Type | Notes |
| --- | --- | --- |
| name, slug | string | both unique |
| price_cents | integer | integer minor units — no floats |
| billing_interval | enum `monthly\|yearly` | `BillingInterval::months()` maps to ends_at arithmetic |
| limits | jsonb, nullable | e.g. `{"max_users": 10, "max_customers": 500}`; missing/`null` key = unlimited |
| is_active | boolean | inactive plans are hidden from the catalog and cannot be subscribed |

### subscriptions
| Column | Type | Notes |
| --- | --- | --- |
| company_id | FK, `cascade` | |
| plan_id | FK, `restrict` | plans with history cannot be deleted |
| status | enum `active\|expired\|cancelled` | |
| starts_at / ends_at | timestamps | `ends_at` nullable (open-ended never happens via API, but schema tolerates it) |
| cancelled_at | timestamp, nullable | |

**Constraints (the interesting part):**

- `subscriptions_one_active_per_company_unique` — partial unique index on `(company_id)` **WHERE status = 'active'**. The application also serializes via row locks, but the database is the final backstop against double-activation.
- `subscriptions_period_check` — check constraint `ends_at > starts_at` (PostgreSQL-only; the SQLite test build omits it and the dedicated test skips accordingly).
- Indexes: `(company_id, status)` for the hot "active subscription" lookup, `(status, ends_at)` for the expiration sweep.

### customers
| Column | Type | Notes |
| --- | --- | --- |
| company_id | FK, `cascade` | |
| name | string | |
| email | string | |
| phone | string, nullable | |
| status | enum `active\|inactive` | |
| soft deletes | | |

- `customers_company_email_unique` — partial unique index on `(company_id, email)` **WHERE deleted_at IS NULL**: emails unique per tenant among live rows, and soft-deleted emails can be reused.
- Index on `(company_id, status)` for filtered lists.

### usage_records
| Column | Type | Notes |
| --- | --- | --- |
| company_id | FK | |
| feature | string(50) | e.g. `max_customers` |
| delta | integer | signed usage delta |
| metadata | jsonb, nullable | |
| recorded_at | timestamp | no `timestamps` on this table |

Schema-first scaffold for usage analytics; the API does not write it yet (documented limitation).

## Query optimization

- Every query selects explicit columns (`select([...])`); no `SELECT *` anywhere.
- Eager loads are constrained (`$subscription->load('plan')`, with FK columns included).
- Aggregates over models: dashboard counts use `count()`, the sweep uses one bulk `UPDATE`, limit checks use `count()` — never `get()->count()`.
- Pagination caps `per_page` at 100 to bound query cost.

## Fixtures

- `DatabaseSeeder`: 3 plans, Acme Ltd demo tenant (owner/admin/member), Passport personal access client.
- Model factories support every table, including subscription states (active/expired/cancelled) and custom limit payloads for limit tests.
