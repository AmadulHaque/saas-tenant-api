<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Concerns\ResolvesTenantCompany;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Billing', description: 'Plan purchase and invoices (owner only).')]
class InvoiceIndexController extends Controller
{
    use ResolvesTenantCompany;

    /**
     * List the company's invoices, newest first (owner only).
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Invoice::class);

        $company = $this->resolveCompany($request);

        $invoices = Invoice::query()
            ->select(['id', 'company_id', 'subscription_id', 'plan_id', 'plan_name', 'amount_cents', 'currency', 'status', 'gateway', 'gateway_reference', 'paid_at', 'created_at'])
            ->where('company_id', $company->id)
            ->when($request->filled('status'), fn ($query) => $query->where(
                'status',
                mb_strtolower(trim($request->string('status')->toString()))
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return InvoiceResource::collection($invoices);
    }
}
