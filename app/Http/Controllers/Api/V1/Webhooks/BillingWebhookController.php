<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\Billing\PaymentResult;
use App\Services\BillingService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

#[Group('Webhooks', description: 'Server-to-server gateway callbacks (signature verified).')]
class BillingWebhookController extends Controller
{
    /**
     * Settle a pending invoice from an asynchronous gateway decision.
     *
     * The raw body must be HMAC-SHA256 signed with the billing webhook
     * secret and sent in the X-Signature header. Deliveries are idempotent.
     */
    public function __invoke(Request $request, BillingService $billing): JsonResponse
    {
        $secret = (string) Config::string('billing.webhook_secret', '');

        if ($secret === '') {
            abort(SymfonyResponse::HTTP_SERVICE_UNAVAILABLE, 'Billing webhook is not configured.');
        }

        $payload = $request->getContent();

        $signature = (string) $request->headers->get('X-Signature', '');

        if (! hash_equals(hash_hmac('sha256', $payload, $secret), $signature)) {
            Log::warning('Billing webhook signature mismatch');

            abort(SymfonyResponse::HTTP_UNAUTHORIZED, 'Invalid webhook signature.');
        }

        $validated = $request->validate([
            'invoice_id' => ['required', 'integer'],
            'status' => ['required', 'in:paid,failed'],
            'reference' => ['sometimes', 'string', 'max:255'],
        ]);

        $invoice = Invoice::query()->find($validated['invoice_id']);

        if ($invoice === null) {
            abort(SymfonyResponse::HTTP_NOT_FOUND, 'Invoice not found.');
        }

        $result = new PaymentResult(
            InvoiceStatus::from($validated['status']),
            $validated['reference'] ?? null,
        );

        $settled = $billing->settle($invoice, $result);

        return response()->json([
            'invoice' => new InvoiceResource($settled),
        ]);
    }
}
