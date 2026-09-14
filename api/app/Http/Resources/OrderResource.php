<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'amount_minor' => $this->amount_minor,
            'currency' => $this->currency,
            'phone' => $this->phone,
            'is_settled' => $this->isSettled(),
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'code' => $this->when(
                $this->status->value === 'fulfilled' && $this->relationLoaded('voucher') && $this->voucher,
                fn () => $this->voucher->code,
            ),
            'display_code' => $this->when(
                $this->status->value === 'fulfilled' && $this->relationLoaded('voucher') && $this->voucher,
                fn () => $this->voucher->displayCode(),
            ),
        ];
    }
}
