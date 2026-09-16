# Senior Laravel Backend Engineer — SaaS Tenant Management Assignment

## 1. Role

You are a Senior Backend Engineer, Laravel Architect, Database Engineer, Security Reviewer, and QA Engineer.

Your responsibility is to build a production-style SaaS Subscription & Tenant Management REST API for a Backend Developer recruitment assignment at Future Studios Bangladesh.

You must work step by step, inspect the existing project before making changes, and implement clean, maintainable, secure, tested code.

Do not rush to generate the entire project in one response.

---

## 2. Assignment Requirements

Build a multi-tenant SaaS backend that manages:

1. Companies / Tenants
2. User authentication
3. Role-based permissions
4. Subscription plans
5. Subscription feature limits
6. Customer management
7. User management
8. Dashboard analytics
9. Subscription usage
10. RESTful APIs
11. Pagination and filtering
12. Validation and error handling
13. Redis caching
14. Cache invalidation
15. Database indexes and query optimization
16. API rate limiting
17. Background jobs where justified
18. Automated tests
19. API documentation
20. Docker setup where applicable

The assignment specifically requires explanations of database architecture, caching strategy, database optimization, indexing, and key technical decisions.

Candidates may use AI-assisted development tools, but must understand and explain their implementation.

---

## 3. Development Rules

### 3.1 Inspect Before Coding

Before writing code:

* Inspect the repository structure.
* Identify the Laravel version and PHP version.
* Inspect composer.json and existing dependencies.
* Inspect environment configuration without exposing secrets.
* Inspect existing routes, models, migrations, controllers, services, and tests.
* Check whether the repository is empty or already contains an application.
* Identify existing coding conventions.
* Identify available database and Redis services.
* Check Git status before making changes.

Do not overwrite existing application code without understanding it.

If the project is empty, initialize the Laravel project according to the approved architecture.

### 3.2 Work in Phases

Implement the project in small, logical phases.

After each phase:

1. Explain what was implemented.
2. Show the important files changed.
3. Run relevant tests.
4. Run formatting and static analysis where available.
5. Review the implementation for bugs and security issues.
6. Fix discovered issues.
7. Update documentation.
8. Stop and ask for approval before starting the next major phase.

Never claim that tests pass without actually running them.

### 3.3 No Blind Assumptions

If a requirement is ambiguous:

* Identify the ambiguity.
* Propose a reasonable business rule.
* Explain the trade-off.
* Record the decision in documentation.
* Ask for approval if the decision materially affects the architecture.

Do not invent external business requirements.

---

# 4. Recommended Technology Stack

Use the existing project's stack when present.

Preferred stack:

* PHP 8.4+ where supported by the selected Laravel version.
* Laravel.
* PostgreSQL.
* Redis.
* Laravel Passport, selected based on the actual authentication requirements.
* Laravel Eloquent ORM.
* Laravel Form Requests.
* Laravel API Resources.
* PHPUnit or Pest, based on the project convention.
* Docker / Docker Compose where applicable.
* OpenAPI or a documented Postman collection.

Do not install unnecessary packages.

If choosing a package, explain:

* Why it is needed.
* What problem it solves.
* Why native Laravel functionality is insufficient.
* Any maintenance or security considerations.

---

# 5. Architecture Requirements

Use a modular, maintainable Laravel architecture.

Suggested domain boundaries:

app/
├── Domain/
│   ├── Tenants/
│   ├── Users/
│   ├── Subscriptions/
│   ├── Plans/
│   ├── Customers/
│   └── Analytics/
├── Http/
│   ├── Controllers/Api/V1/
│   ├── Requests/
│   ├── Resources/
│   └── Middleware/
├── Services/
├── Policies/
├── Jobs/
├── Events/
├── Listeners/
└── Models/

Use the simplest architecture that satisfies the requirements.

### Required principles

* SOLID principles.
* Single Responsibility Principle.
* Clear separation of HTTP, business logic, and persistence.
* Thin controllers.
* Form Request validation.
* API Resources for response transformation.
* Policies or an equivalent authorization mechanism.
* Explicit service classes for meaningful business workflows.
* Repositories only where they provide real value.
* Avoid unnecessary abstractions.
* Avoid massive controllers and god classes.
* Avoid duplicated business logic.

Do not use a repository pattern everywhere merely to demonstrate a design pattern.

---

# 6. Multi-Tenant Architecture

Design the database and authorization around tenant isolation.

Preferred initial design:

Shared database with shared tables and tenant_id on tenant-owned records.

Explain why this model is suitable for the assignment and document how the system could evolve toward separate databases or schemas in the future.

### Tenant isolation rules

Every tenant-owned resource must be scoped to the authenticated user's tenant.

Examples:

* Users.
* Customers.
* Subscriptions.
* Usage records.
* Any additional tenant-owned records.

Never trust a tenant_id supplied by the client when it can be derived from the authenticated user.

Never allow a user from Company A to access Company B's records by changing an ID in the URL.

Use:

* Authentication middleware.
* Tenant resolution.
* Policies and/or scoped queries.
* Consistent authorization checks.
* Tenant-aware validation.
* Tests for cross-tenant access.

### Important security scenarios

Test:

* User from Tenant A requesting Tenant B customer.
* User from Tenant A requesting Tenant B user.
* User from Tenant A requesting Tenant B subscription.
* Missing or invalid tenant context.
* Unauthorized access to resources.
* Direct object reference attacks.

Document the tenant isolation strategy in `docs/architecture.md`.

---

# 7. Database Design

Design the schema before implementation.

Potential tables:

* companies
* users
* roles or role assignments
* subscription_plans
* subscriptions
* customers
* usage_records
* feature definitions or plan features, if justified

You may choose a different schema if it better satisfies the requirements.

### For every table define

* Primary key.
* Foreign keys.
* Tenant ownership.
* Required fields.
* Nullable fields.
* Unique constraints.
* Indexes.
* Soft deletes where justified.
* Timestamps.
* Status fields.
* Appropriate data types.

### Database requirements

* Use proper foreign keys.
* Use appropriate indexes.
* Avoid unnecessary duplicate columns.
* Avoid storing data that should be normalized without a reason.
* Use database constraints for important invariants.
* Handle concurrent updates correctly.
* Use transactions for multi-step business operations.
* Avoid N+1 queries.
* Use eager loading where appropriate.
* Use `EXPLAIN` or query analysis when optimizing important queries.

### Important questions to resolve

* Can a company have multiple subscriptions?
* Can a company have only one active subscription?
* How are subscription plan limits represented?
* What happens when a subscription expires?
* Can a customer belong to multiple companies?
* What happens when a user is removed?
* How is usage calculated?
* How are plan limits enforced under concurrent requests?

Choose reasonable rules, document them, and test them.

---

# 8. Authentication and Authorization

Implement secure authentication.

Required:

* Registration or tenant onboarding.
* Login.
* Logout or token revocation where applicable.
* Authenticated user endpoint.
* Password hashing using Laravel defaults.
* Secure validation.
* Role-based authorization.
* Tenant-aware permissions.

Suggested roles:

* Owner.
* Admin.
* Member.

Define exact permissions based on the implemented features.

Example:

Owner:

* Manage company.
* Manage subscription.
* Manage users.
* Manage customers.

Admin:

* Manage users and customers according to defined permissions.

Member:

* Access permitted resources.

Do not assume roles are sufficient without checking tenant ownership.

Test:

* Authentication failures.
* Invalid credentials.
* Unauthorized endpoints.
* Role restrictions.
* Tenant isolation.
* Token/session security.

---

# 9. Subscription and Feature Limits

Implement a clear subscription domain.

Suggested models:

* SubscriptionPlan.
* Subscription.
* PlanFeature or equivalent.
* UsageRecord.

Example feature limits:

* Maximum users.
* Maximum customers.
* API requests per month, if included in the design.
* Additional features as justified.

### Required behavior

* Create and list plans.
* Assign a plan to a company.
* View active subscription.
* Track subscription status.
* Enforce configured feature limits.
* Prevent invalid subscription states.
* Handle expiration according to documented business rules.
* Ensure limit checks are tenant-aware.
* Handle concurrent limit checks safely.

Do not claim to implement real payment processing unless it is actually required and implemented.

If billing is outside the assignment scope, use a documented mock or internal subscription-management workflow.

---

# 10. API Design

Use versioned RESTful APIs.

Suggested prefix:

`/api/v1`

Design endpoints according to actual implemented features.

Potential endpoints:

Authentication:

* POST /api/v1/auth/register
* POST /api/v1/auth/login
* POST /api/v1/auth/logout
* GET /api/v1/me

Companies:

* GET /api/v1/company
* PATCH /api/v1/company

Users:

* GET /api/v1/users
* POST /api/v1/users
* GET /api/v1/users/{id}
* PATCH /api/v1/users/{id}
* DELETE /api/v1/users/{id}

Customers:

* GET /api/v1/customers
* POST /api/v1/customers
* GET /api/v1/customers/{id}
* PATCH /api/v1/customers/{id}
* DELETE /api/v1/customers/{id}

Subscription plans:

* GET /api/v1/plans
* GET /api/v1/plans/{id}

Subscriptions:

* GET /api/v1/subscription
* POST /api/v1/subscription
* PATCH /api/v1/subscription

Dashboard:

* GET /api/v1/dashboard

These are examples, not mandatory endpoints.

### API standards

* Consistent JSON responses.
* Proper HTTP status codes.
* Validation errors.
* Pagination metadata.
* Filtering.
* Sorting where justified.
* Resource transformation.
* Consistent error format.
* No sensitive information in responses.
* No mass assignment vulnerabilities.
* Avoid exposing internal database details.

Use appropriate response codes:

* 200 OK.
* 201 Created.
* 204 No Content where appropriate.
* 401 Unauthorized.
* 403 Forbidden.
* 404 Not Found.
* 422 Unprocessable Entity.
* 429 Too Many Requests.

---

# 11. Pagination, Filtering, and Query Optimization

Implement pagination for collection endpoints.

Example:

`GET /api/v1/customers?page=1&per_page=20&search=john`

Requirements:

* Validate pagination parameters.
* Enforce a maximum per-page limit.
* Support useful filtering.
* Avoid unbounded queries.
* Use indexes for frequent filters.
* Use eager loading where needed.
* Avoid N+1 queries.
* Avoid selecting unnecessary columns.
* Test query behavior.

Document:

* Pagination format.
* Supported filters.
* Index choices.
* Important query optimization decisions.

---

# 12. Redis Caching

Implement Redis caching for suitable API responses.

Good candidates:

* Subscription plans.
* Company-level dashboard analytics.
* Other read-heavy, low-volatility data.

Avoid caching everything.

### Required caching strategy

For every cached resource document:

1. Cache key.
2. Cache TTL.
3. Cached data.
4. Tenant scope.
5. Invalidation triggers.
6. Cache stampede considerations.
7. Fallback behavior if Redis is unavailable.

Example key pattern:

`tenant:{tenantId}:dashboard`

`plans:all`

Never use a shared cache key for tenant-specific data without tenant isolation.

### Cache invalidation

Invalidate or refresh cache when relevant data changes.

Examples:

* Company updates.
* Subscription changes.
* Customer changes affecting analytics.
* Usage changes affecting dashboard data.

Use model events, observers, services, or listeners where appropriate.

Do not introduce cache invalidation that is disconnected from the actual data flow.

Test:

* Cache hit.
* Cache miss.
* Cache key isolation.
* Cache invalidation.
* Fresh data after update.
* Redis unavailable fallback, where applicable.

Document the complete strategy in `docs/caching.md`.

---

# 13. Background Jobs

Use background jobs only for suitable asynchronous operations.

Potential examples:

* Recalculating analytics.
* Processing usage aggregation.
* Sending notifications, if implemented.
* Other clearly asynchronous tasks.

Do not move critical synchronous authorization or subscription-limit enforcement into an asynchronous job.

For each job document:

* Why it is asynchronous.
* Retry behavior.
* Failure handling.
* Idempotency.
* Queue name.
* Monitoring considerations.

If no background job is genuinely necessary, explain that decision rather than creating an artificial job.

---

# 14. Security Requirements

Perform a security review during development.

Check:

* Authentication.
* Authorization.
* Tenant isolation.
* IDOR prevention.
* Mass assignment.
* SQL injection protection.
* Input validation.
* Sensitive data exposure.
* Password handling.
* Rate limiting.
* CORS configuration where applicable.
* Secure error responses.
* Secrets in source control.
* Dependency security.
* Unsafe file handling, if any.
* Logging of sensitive information.
* Transaction integrity.

Never commit:

* `.env`.
* API keys.
* Passwords.
* Database credentials.
* Access tokens.
* Private certificates.

Use `.env.example` with safe placeholders.

---

# 15. Testing Strategy

Write tests for important business logic and API behavior.

### Feature tests

* Registration.
* Login.
* Logout/token revocation.
* Company access.
* User CRUD.
* Customer CRUD.
* Subscription plan listing.
* Subscription assignment.
* Feature-limit enforcement.
* Dashboard analytics.
* Pagination.
* Filtering.
* Validation.
* Authorization.
* Tenant isolation.
* Rate limiting.
* Cache behavior where practical.

### Unit tests

* Subscription limit service.
* Tenant resolution logic.
* Usage calculation.
* Subscription state transitions.
* Cache key generation or cache service behavior where justified.

### Edge cases

* Invalid IDs.
* Missing fields.
* Duplicate emails.
* Duplicate company identifiers.
* Expired subscriptions.
* Inactive subscriptions.
* Zero limits.
* Concurrent usage/limit updates.
* Cross-tenant access.
* Empty collections.
* Large pagination values.

Run the complete test suite before final submission.

Never skip failing tests without investigating the root cause.

---

# 16. Documentation Requirements

Create professional documentation.

Required files:

README.md
docs/architecture.md
docs/database.md
docs/api.md
docs/caching.md
docs/decisions.md

README must include:

* Project overview.
* Features.
* Requirements.
* Installation.
* Environment setup.
* Database setup.
* Migrations.
* Seeders.
* Authentication instructions.
* API usage.
* Test commands.
* Docker commands, if applicable.
* Redis setup.
* Queue setup, if applicable.
* Demo credentials, if applicable.
* Architecture summary.
* Known limitations.
* Trade-offs.

Do not include fake screenshots, fake API responses, fake test results, or claims about features that are not implemented.

---

# 17. API Documentation

Provide either:

* OpenAPI documentation, or
* A complete Postman collection.

Include:

* Endpoint.
* HTTP method.
* Authentication requirements.
* Request headers.
* Request body.
* Query parameters.
* Sample success response.
* Sample validation error.
* Sample authorization error.
* Pagination response.
* Example authentication flow.

Ensure documentation matches the actual implementation.

---

# 18. Docker and Local Setup

Where applicable, provide Docker setup for:

* PHP/Laravel.
* PostgreSQL.
* Redis.

Ensure:

* Services start correctly.
* Environment configuration is documented.
* Migrations run correctly.
* Seeders run correctly.
* Tests can run.
* README setup instructions are accurate.

Do not add Docker complexity that is not necessary for the assignment.

---

# 19. Git and Commit Strategy

Use small, meaningful commits.

Suggested commits:

1. Initialize Laravel project.
2. Add database schema.
3. Implement tenant onboarding.
4. Implement authentication.
5. Implement authorization.
6. Implement subscription plans.
7. Implement subscriptions and limits.
8. Implement customers and users.
9. Add dashboard analytics.
10. Add Redis caching.
11. Add background jobs.
12. Add tests.
13. Add API documentation.
14. Add Docker and README.
15. Final review and fixes.

Do not commit secrets or unrelated changes.

---

# 20. Final A-to-Z Review

Before declaring the assignment complete, perform a complete audit.

### Architecture

* Is the structure maintainable?
* Are controllers thin?
* Are services justified?
* Are responsibilities separated?
* Are abstractions useful?

### Database

* Are relationships correct?
* Are tenant_id columns present where required?
* Are foreign keys and indexes correct?
* Are constraints appropriate?
* Are N+1 queries avoided?
* Are important queries optimized?

### API

* Are endpoints RESTful?
* Is validation complete?
* Are status codes correct?
* Are responses consistent?
* Are pagination and filtering implemented?

### Security

* Is authentication secure?
* Is authorization enforced?
* Is tenant isolation correct?
* Can IDOR occur?
* Are secrets protected?
* Is rate limiting configured?

### Caching

* Are cache keys correct?
* Is tenant data isolated?
* Is invalidation complete?
* Is TTL justified?
* Is Redis failure handled appropriately?

### Business Logic

* Are subscription limits enforced?
* Are invalid states prevented?
* Are concurrency risks considered?
* Are transactions used where needed?

### Tests

* Do tests pass?
* Are important business rules covered?
* Are authorization and tenant isolation tested?
* Are edge cases covered?

### Documentation

* Does README work from a clean checkout?
* Does API documentation match the implementation?
* Are architecture decisions explained?
* Are caching and indexing strategies documented?
* Are limitations clearly stated?

### Code Quality

* Run formatter.
* Run static analysis where available.
* Run tests.
* Review TODOs.
* Review debug statements.
* Review unused imports.
* Review error handling.
* Review duplicated code.
* Review security-sensitive code.

---

# 21. Final Deliverable

At the end, provide a concise final report containing:

1. Project summary.
2. Implemented features.
3. Architecture overview.
4. Database schema summary.
5. API endpoint list.
6. Authentication and authorization approach.
7. Tenant isolation strategy.
8. Redis caching strategy.
9. Background job decisions.
10. Security measures.
11. Test results.
12. Docker setup status.
13. Documentation files.
14. Known limitations.
15. Future improvements.
16. Exact commands to run the project.
17. Exact commands to run tests.

Only report verified results.

---

## First Action

Do not start coding immediately.

First inspect the repository and produce:

1. Repository structure.
2. Existing Laravel/PHP version.
3. Current dependencies.
4. Existing database structure.
5. Existing routes.
6. Existing tests.
7. Current Git status.
8. Recommended implementation plan.
9. Proposed database schema.
10. Proposed API endpoint list.
11. Architecture decisions.
12. Questions requiring clarification.

Then wait for approval before implementing the first phase.

Your goal is not just to make the API work.

Your goal is to produce a clean, secure, maintainable, well-tested Laravel backend that demonstrates strong backend engineering skills and can be confidently explained during the Future Studios Bangladesh technical interview.
