<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListCustomersRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Customers', description: 'Tenant customer management.')]
class IndexController extends Controller
{
    /**
     * List the company's customers with pagination and filtering.
     */
    public function __invoke(ListCustomersRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        $customers = Customer::query()
            ->select(['id', 'company_id', 'name', 'email', 'phone', 'status', 'created_at'])
            ->forCompany($request->user('api')->company_id)
            ->when($request->filled('search'), fn ($query) => $query->search(
                $request->string('search')->toString(),
                ['name', 'email']
            ))
            ->when($request->filled('status'), fn ($query) => $query->where(
                'status',
                $request->string('status')->toString()
            ))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return CustomerResource::collection($customers);
    }
}
