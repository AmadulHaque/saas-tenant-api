<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

#[Group('Customers', description: 'Tenant customer management.')]
class DestroyController extends Controller
{
    /**
     * Soft-delete a customer of the authenticated user's company.
     */
    public function __invoke(Request $request, int $id): Response
    {
        $customer = Customer::query()
            ->forCompany($request->user('api')->company_id)
            ->find($id);

        abort_if($customer === null, 404);

        $this->authorize('delete', $customer);

        $customer->delete();

        return response()->noContent();
    }
}
