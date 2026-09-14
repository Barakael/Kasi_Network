<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Site
 */
class SiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'ssid' => $this->ssid,
            'nas_identifier' => $this->nas_identifier,
            'timezone' => $this->timezone,
            'status' => $this->status,
            'address' => $this->address,
            'nas_devices_count' => $this->whenCounted('nasDevices'),
        ];
    }
}
