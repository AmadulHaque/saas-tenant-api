# Database Query Optimization

**Database queries are the single biggest performance lever. Treat every query as critical.**

- Always use `select()` — never fetch `SELECT *`. Specify only the columns needed.
- Use `qualifyColumn()` when joins, subqueries, or ambiguous column names are involved (`id`, `name`, `status`, `created_at`).
- Constrain eager loads with `select()` inside `with()` callbacks. Always include the foreign key column.
- Use aggregates (`count`, `exists`, `sum`) instead of loading full models when only a value is needed.

```php
// Bad
$company = Company::where('slug', $slug)->first();

// Good
$company = Company::query()
    ->select(['id', 'owner_id', 'name', 'slug'])
    ->where('slug', $slug)
    ->first();

// Good — qualified columns in joins
$company = new Company;

Company::query()
    ->select([$company->qualifyColumn('id'), $company->qualifyColumn('name')])
    ->join('users', 'users.company_id', '=', $company->qualifyColumn('id'))
    ->get();

// Good — constrained eager load
Company::query()
    ->select(['id', 'name', 'slug'])
    ->with(['members' => fn ($query) => $query->select(['id', 'company_id', 'name'])])
    ->get();
```
