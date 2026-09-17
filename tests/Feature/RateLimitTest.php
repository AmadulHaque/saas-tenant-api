<?php

use App\Models\Company;

test('authenticated endpoints are rate limited after 60 requests per minute', function (): void {
    $company = Company::factory()->withOwner()->create();
    $owner = $company->owner;

    $last = null;
    for ($i = 0; $i < 61; $i++) {
        $last = $this->actingAs($owner, 'api')->getJson('/api/v1/me');
    }

    $last->assertTooManyRequests()->assertHeader('Retry-After');
});
