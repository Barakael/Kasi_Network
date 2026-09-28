<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'phone' => $this->maskedPhone(),
            'phone_local' => $this->localPhone(),
            'status' => $this->resource->getAttribute('presence') ?? 'kimya',
            'last_package' => $this->resource->getAttribute('last_package'),
            'paid_via' => $this->resource->getAttribute('paid_via'),
            'has_unused_voucher' => (bool) $this->resource->getAttribute('has_unused_voucher'),
            'session_id' => $this->resource->getAttribute('session_id'),
            'site' => new SiteResource($this->whenLoaded('site')),
            'last_mac' => $this->last_mac,
            'first_seen_at' => $this->first_seen_at?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
