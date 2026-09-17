<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowController extends Controller
{
    /**
     * Show a customer of the authenticated user's company.
     */
    public function __invoke(Request $request, int $id): JsonResponse
    {
        $customer = Customer::query()
            ->forCompany($request->user('api')->company_id)
            ->find($id);

        abort_if($customer === null, 404);

        $this->authorize('view', $customer);

        return response()->json([
            'customer' => new CustomerResource($customer),
        ]);
    }
}
