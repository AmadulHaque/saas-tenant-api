<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property CustomerStatus $status
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'email',
        'phone',
        'status',
    ];

    /**
     * The company (tenant) that owns this customer.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope to a single company's customers.
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
            'status' => CustomerStatus::class,
        ];
    }
}
