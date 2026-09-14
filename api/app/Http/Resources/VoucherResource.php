<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A voucher as the console sees it.
 *
 * The code itself is not here. Listing vouchers is a routine operation -- checking
 * how a batch is selling, finding an expiry -- and a code is a live credential, so
 * it is only returned by the print layout and by the explicit reveal endpoint,
 * both of which are audited. What appears instead is the last group of characters,
 * enough for staff to match a card a caller is reading out.
 *
 * @mixin Voucher
 */
class VoucherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code_suffix' => $this->code_suffix,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_redeemable' => $this->isRedeemable(),

            'validity_seconds' => $this->validity_seconds,
            'duration_seconds' => $this->duration_seconds,
            'data_cap_bytes' => $this->data_cap_bytes,
            'device_limit' => $this->device_limit,
            'price_minor' => $this->price_minor,

            'bound_mac' => $this->bound_mac,
            'first_used_at' => $this->first_used_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'shelf_expires_at' => $this->shelf_expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'plan' => new PlanResource($this->whenLoaded('plan')),
            'batch' => new VoucherBatchResource($this->whenLoaded('batch')),
            'devices' => VoucherDeviceResource::collection($this->whenLoaded('devices')),

            'usage' => $this->whenLoaded('usage', fn (): array => [
                'seconds_used' => (int) $this->usage->seconds_used,
                'bytes_used' => (int) $this->usage->bytes_in + (int) $this->usage->bytes_out,
                'session_count' => (int) $this->usage->session_count,
                'last_session_at' => $this->usage->last_session_at?->toIso8601String(),
            ]),
        ];
    }
}
