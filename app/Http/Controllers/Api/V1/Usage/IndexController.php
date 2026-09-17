<?php

namespace App\Http\Controllers\Api\V1\Usage;

use App\Http\Controllers\Concerns\ResolvesTenantCompany;
use App\Http\Controllers\Controller;
use App\Http\Resources\UsageResource;
use App\Models\UsageRecord;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Usage', description: 'Append-only feature usage ledger (owner/admin).')]
class IndexController extends Controller
{
    use ResolvesTenantCompany;

    /**
     * List the company's usage records, newest first (owner/admin only).
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', UsageRecord::class);

        $company = $this->resolveCompany($request);

        $records = UsageRecord::query()
            ->select(['id', 'company_id', 'feature', 'delta', 'metadata', 'recorded_at'])
            ->where('company_id', $company->id)
            ->when($request->filled('feature'), fn ($query) => $query->where(
                'feature',
                mb_strtolower(trim($request->string('feature')->toString()))
            ))
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return UsageResource::collection($records);
    }
}
