<?php

declare(strict_types=1);

namespace App\Domain\Radius;

use App\Models\Nas;
use App\Models\NasDevice;

/**
 * Keeps FreeRADIUS's `nas` table in lock-step with operator routers.
 *
 * FreeRADIUS loads clients from `nas` when read_clients is on, so adding a
 * router in the console is enough to accept its Access-Requests without editing
 * clients.conf. The secret is stored here in cleartext because the RADIUS
 * protocol requires it; NasDevice holds the encrypted copy the console shows.
 */
final readonly class NasSynchroniser
{
    public function sync(NasDevice $device): void
    {
        Nas::query()->updateOrInsert(
            ['nasname' => $device->nasname],
            [
                'shortname' => substr($device->name, 0, 32),
                'type' => 'other',
                'secret' => $device->shared_secret,
                'description' => 'tenant:'.$device->tenant_id.' site:'.$device->site_id,
            ],
        );
    }

    public function forget(NasDevice $device): void
    {
        Nas::query()->where('nasname', $device->nasname)->delete();
    }
}
