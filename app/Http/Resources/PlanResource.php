<?php

namespace App\Http\Resources;

use App\Enums\BillingInterval;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $price_cents
 * @property BillingInterval $billing_interval
 * @property array<string, int|null>|null $limits
 */
class PlanResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'price_cents' => $this->price_cents,
            'billing_interval' => $this->billing_interval->value,
            'limits' => $this->limits,
        ];
    }
}
