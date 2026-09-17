<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $feature
 * @property int $delta
 * @property array<string, mixed>|null $metadata
 * @property Carbon $recorded_at
 */
class UsageResource extends JsonResource
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
            'feature' => $this->feature,
            'delta' => $this->delta,
            'metadata' => $this->metadata,
            'recorded_at' => $this->recorded_at->toISOString(),
        ];
    }
}
