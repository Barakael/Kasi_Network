<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'billing_period' => $this->billing_period->value,
            'billing_period_label' => $this->billing_period->label(),
            'validity_seconds' => $this->validity_seconds,
            'duration_seconds' => $this->duration_seconds,
            'data_cap_bytes' => $this->data_cap_bytes,
            'price_minor' => $this->price_minor,
            'device_limit' => $this->device_limit,

            // Console-only fields; the portal has no use for them and should not
            // be shown inactive bundles at all. Rate limits stay off the portal
            // so a silent throttle is not advertised to customers.
            'rate_limit_down_kbps' => $this->when($request->user() !== null, $this->rate_limit_down_kbps),
            'rate_limit_up_kbps' => $this->when($request->user() !== null, $this->rate_limit_up_kbps),
            'on_quota_exhausted' => $this->when($request->user() !== null, $this->on_quota_exhausted->value),
            'throttle_down_kbps' => $this->when($request->user() !== null, $this->throttle_down_kbps),
            'throttle_up_kbps' => $this->when($request->user() !== null, $this->throttle_up_kbps),
            'shelf_life_days' => $this->when($request->user() !== null, $this->shelf_life_days),
            'is_active' => $this->when($request->user() !== null, $this->is_active),
            'is_sold_online' => $this->when($request->user() !== null, $this->is_sold_online),
            'sort_order' => $this->when($request->user() !== null, $this->sort_order),
        ];
    }
}
