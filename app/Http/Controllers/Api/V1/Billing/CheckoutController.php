<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Concerns\AuthorizesSubscription;
use App\Http\Controllers\Concerns\ResolvesTenantCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Services\BillingService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

#[Group('Billing', description: 'Plan purchase and invoices (owner only).')]
class CheckoutController extends Controller
{
    use AuthorizesSubscription;
    use ResolvesTenantCompany;

    /**
     * Purchase a plan: charge the gateway and activate the subscription.
     *
     * Returns 201 with the subscription when the charge settles instantly,
     * 202 while the gateway confirms asynchronously (the billing webhook
     * activates the subscription on confirmation), or 402 when declined.
     */
    public function __invoke(CheckoutRequest $request, BillingService $billing): JsonResponse
    {
        $this->authorizeSubscription('create', $this->resolveCompany($request));

        $company = $this->resolveCompany($request);
        $plan = $request->plan();

        try {
            $invoice = $billing->checkout($company, $plan);
        } catch (ValidationException $exception) {
            // E.g. repurchasing the currently active plan; the invoice is
            // rolled back with the transaction.
            throw $exception;
        }

        if ($invoice->status->value === 'failed') {
            return response()->json([
                'message' => __('http.payment_failed'),
                'invoice' => new InvoiceResource($invoice),
            ], SymfonyResponse::HTTP_PAYMENT_REQUIRED);
        }

        if ($invoice->status->value === 'pending') {
            return response()->json([
                'invoice' => new InvoiceResource($invoice),
            ], SymfonyResponse::HTTP_ACCEPTED);
        }

        $subscription = Subscription::query()->findOrFail($invoice->subscription_id);

        return response()->json([
            'invoice' => new InvoiceResource($invoice),
            'subscription' => new SubscriptionResource($subscription->load('plan')),
        ], SymfonyResponse::HTTP_CREATED);
    }
}
