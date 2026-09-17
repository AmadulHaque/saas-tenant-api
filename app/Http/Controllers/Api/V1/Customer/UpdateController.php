<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;

class UpdateController extends Controller
{
    /**
     * Update a customer of the authenticated user's company.
     */
    public function __invoke(UpdateCustomerRequest $request, int $id): JsonResponse
    {
        $customer = Customer::query()
            ->forCompany($request->user('api')->company_id)
            ->find($id);

        abort_if($customer === null, 404);

        $this->authorize('update', $customer);

        $customer->fill($request->validated());
        $customer->save();

        return response()->json([
            'customer' => new CustomerResource($customer),
        ]);
    }
}
