<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // The public identifier; the integer key is never exposed.
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'code_prefix' => $this->code_prefix,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'portal_name' => $this->portal_name,
            'primary_color' => $this->primary_color,
            'support_phone' => $this->support_phone,
            /*
             * Whether payments are configured, never the credentials themselves.
             * The console needs this to decide if online bundle sales can be
             * offered at all.
             */
            'accepts_online_payments' => $this->acceptsOnlinePayments(),
        ];
    }
}
