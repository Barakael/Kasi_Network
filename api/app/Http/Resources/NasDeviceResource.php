<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\NasDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NasDevice
 */
class NasDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nasname' => $this->nasname,
            'coa_port' => $this->coa_port,
            'status' => $this->status,
            'has_api' => $this->hasApiCredentials(),
            'api_host' => $this->api_host,
            'api_port' => $this->api_port,
            'model' => $this->model,
            'routeros_version' => $this->routeros_version,
            'site' => new SiteResource($this->whenLoaded('site')),
        ];
    }
}
