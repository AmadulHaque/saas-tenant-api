<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Billing', description: 'Plan purchase and invoices (owner only).')]
class InvoiceShowController extends Controller
{
    /**
     * Show one of the company's invoices (owner only).
     */
    public function __invoke(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        return response()->json([
            'invoice' => new InvoiceResource($invoice),
        ]);
    }
}
