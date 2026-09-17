<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
])]
#[Hidden(['password'])]
class User extends Authenticatable implements OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the company (tenant) the user belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Constrain the query to the given company.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForCompany(Builder $query, int $companyId): void
    {
        $query->where('company_id', $companyId);
    }

    /**
     * Case-insensitive search across the given columns.
     *
     * PostgreSQL LIKE is case-sensitive, so ilike is used there; SQLite
     * LIKE is already case-insensitive.
     *
     * @param  Builder<self>  $query
     * @param  list<string>  $columns
     */
    public function scopeSearch(Builder $query, string $term, array $columns): void
    {
        $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $pattern = '%'.addcslashes($term, '\\%_').'%';

        $query->where(function (Builder $query) use ($columns, $operator, $pattern): void {
            foreach ($columns as $index => $column) {
                $query->{$index === 0 ? 'where' : 'orWhere'}($column, $operator, $pattern);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }
}
