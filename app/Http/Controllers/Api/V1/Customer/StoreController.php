<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\CustomerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class StoreController extends Controller
{
    /**
     * Create a customer in the authenticated user's company.
     */
    public function __invoke(StoreCustomerRequest $request, SubscriptionLimitService $limits): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $actor = $request->user('api');

        $customer = $limits->enforce($actor->company, 'max_customers', fn (): Customer => Customer::create([
            ...$request->validated(),
            'company_id' => $actor->company_id,
            'status' => $request->string('status')->toString() ?: CustomerStatus::Active->value,
        ]));

        return response()->json([
            'customer' => new CustomerResource($customer),
        ], SymfonyResponse::HTTP_CREATED);
    }
}
