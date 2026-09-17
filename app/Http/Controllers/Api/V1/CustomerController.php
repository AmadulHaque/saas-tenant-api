<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CustomerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListCustomersRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CustomerController extends Controller
{
    /**
     * List the company's customers with pagination and filtering.
     */
    public function index(ListCustomersRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        $customers = Customer::query()
            ->select(['id', 'company_id', 'name', 'email', 'phone', 'status', 'created_at'])
            ->where('company_id', $request->user('api')->company_id)
            ->when($request->filled('search'), fn ($query) => $query->where(function ($query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $query->where('name', 'like', $term)->orWhere('email', 'like', $term);
            }))
            ->when($request->filled('status'), fn ($query) => $query->where(
                'status',
                $request->string('status')->toString()
            ))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return CustomerResource::collection($customers);
    }

    /**
     * Create a customer in the authenticated user's company.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $customer = Customer::create([
            ...$request->validated(),
            'company_id' => $request->user('api')->company_id,
            'status' => $request->string('status')->toString() ?: CustomerStatus::Active->value,
        ]);

        return response()->json([
            'customer' => new CustomerResource($customer),
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Show a customer of the authenticated user's company.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $customer = Customer::query()
            ->where('company_id', $request->user('api')->company_id)
            ->find($id);

        abort_if($customer === null, 404);

        $this->authorize('view', $customer);

        return response()->json([
            'customer' => new CustomerResource($customer),
        ]);
    }

    /**
     * Update a customer of the authenticated user's company.
     */
    public function update(UpdateCustomerRequest $request, int $id): JsonResponse
    {
        $customer = Customer::query()
            ->where('company_id', $request->user('api')->company_id)
            ->find($id);

        abort_if($customer === null, 404);

        $this->authorize('update', $customer);

        $customer->fill($request->validated());
        $customer->save();

        return response()->json([
            'customer' => new CustomerResource($customer),
        ]);
    }

    /**
     * Soft-delete a customer of the authenticated user's company.
     */
    public function destroy(Request $request, int $id): Response
    {
        $customer = Customer::query()
            ->where('company_id', $request->user('api')->company_id)
            ->find($id);

        abort_if($customer === null, 404);

        $this->authorize('delete', $customer);

        $customer->delete();

        return response()->noContent();
    }
}
