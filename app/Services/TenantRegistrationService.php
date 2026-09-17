<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registers a new tenant: company plus its owner, in a single transaction.
 */
class TenantRegistrationService
{
    /**
     * Create a company, its owner, and an initial access token.
     *
     * @param  array{name: string, email: string, password: string, company_name: string}  $data
     * @return array{user: User, company: Company, accessToken: string}
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $company = Company::create([
                'name' => $data['company_name'],
                'slug' => $this->generateSlug($data['company_name']),
            ]);

            $user = new User;
            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'company_id' => $company->id,
                'role' => UserRole::Owner,
                'email_verified_at' => now(),
            ]);
            $user->save();

            $company->forceFill(['owner_id' => $user->id])->save();

            return [
                'user' => $user,
                'company' => $company,
                'accessToken' => $user->createToken('registration')->accessToken,
            ];
        });
    }

    /**
     * Generate a unique slug derived from the company name.
     *
     * The unique constraint on companies.slug is the race-condition backstop;
     * the random suffix keeps collisions vanishingly rare.
     */
    private function generateSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'company';

        do {
            $slug = sprintf('%s-%s', $base, Str::lower(Str::random(6)));
        } while (Company::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
