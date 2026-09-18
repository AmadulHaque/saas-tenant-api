<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $subscription_id
 * @property int $plan_id
 * @property string $plan_name
 * @property int $amount_cents
 * @property string $currency
 * @property string $status
 * @property string $gateway
 * @property string|null $gateway_reference
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 */
class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into a JSON array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan' => [
                'id' => $this->plan_id,
                'name' => $this->plan_name,
            ],
            'subscription_id' => $this->subscription_id,
            'amount' => [
                'cents' => $this->amount_cents,
                'currency' => $this->currency,
            ],
            'status' => $this->status,
            'gateway' => $this->gateway,
            'gateway_reference' => $this->gateway_reference,
            'paid_at' => $this->paid_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
