<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\SubscriptionPlan;
use App\Services\Billing\BillingGateway;
use App\Services\Billing\PaymentResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Billing: purchasing a plan charges the gateway and activates the
 * subscription once the payment is confirmed (synchronously for instant
 * results, asynchronously via the webhook for pending ones).
 */
class BillingService
{
    public function __construct(
        private readonly BillingGateway $gateway,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * Charge the tenant for a plan and activate the subscription on success.
     *
     * Runs inside a company row lock so concurrent checkouts for the same
     * tenant serialize, exactly like direct subscription changes.
     *
     * @throws ValidationException when the plan cannot be purchased
     */
    public function checkout(Company $company, SubscriptionPlan $plan): Invoice
    {
        return DB::transaction(function () use ($company, $plan): Invoice {
            Company::query()->whereKey($company->id)->lockForUpdate()->get();

            $invoice = Invoice::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'amount_cents' => $plan->price_cents,
                'currency' => (string) config('billing.currency', 'usd'),
                'status' => InvoiceStatus::Pending,
                'gateway' => (string) config('billing.gateway', 'fake'),
            ]);

            // Free plans never touch the gateway.
            if ($plan->price_cents === 0) {
                return $this->markPaid($invoice, 'free');
            }

            $result = $this->gateway->charge(
                $plan->price_cents,
                $invoice->currency,
                sprintf('Subscription: %s plan', $plan->name),
            );

            return match ($result->status) {
                InvoiceStatus::Paid => $this->markPaid($invoice, $result->reference),
                InvoiceStatus::Failed => $this->markFailed($invoice, $result->reference),
                InvoiceStatus::Pending => $invoice,
            };
        });
    }

    /**
     * Apply an asynchronous gateway decision to a pending invoice.
     *
     * Idempotent: an invoice that is no longer pending is returned untouched,
     * so duplicate webhook deliveries are safe.
     */
    public function settle(Invoice $invoice, PaymentResult $result): Invoice
    {
        return DB::transaction(function () use ($invoice, $result): Invoice {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== InvoiceStatus::Pending) {
                return $invoice;
            }

            return match ($result->status) {
                InvoiceStatus::Paid => $this->markPaid($locked, $result->reference),
                InvoiceStatus::Failed => $this->markFailed($locked, $result->reference),
                InvoiceStatus::Pending => $locked,
            };
        });
    }

    /**
     * Mark the invoice paid and activate (or switch to) the purchased plan.
     *
     * @throws ValidationException when the plan cannot be assigned
     */
    private function markPaid(Invoice $invoice, ?string $reference): Invoice
    {
        $subscription = $this->subscriptions->subscribe($invoice->company, $invoice->plan);

        $invoice->forceFill([
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
            'gateway_reference' => $reference,
            'subscription_id' => $subscription->id,
        ]);
        $invoice->save();

        return $invoice;
    }

    /**
     * Record the gateway decline; nothing is activated.
     */
    private function markFailed(Invoice $invoice, ?string $reference): Invoice
    {
        $invoice->forceFill([
            'status' => InvoiceStatus::Failed,
            'gateway_reference' => $reference,
        ]);
        $invoice->save();

        return $invoice;
    }
}
