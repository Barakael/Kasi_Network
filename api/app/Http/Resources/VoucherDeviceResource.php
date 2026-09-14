<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\VoucherDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VoucherDevice
 */
class VoucherDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'voucher_id' => $this->voucher_id,
            'mac' => $this->mac,
            'label' => $this->label,
            'vendor' => $this->vendor,
            'status' => $this->status,
            'created_via' => $this->created_via,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'voucher_suffix' => $this->whenLoaded('voucher', fn () => $this->voucher?->code_suffix),
        ];
    }
}
