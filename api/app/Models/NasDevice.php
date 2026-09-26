<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\BelongsToTenant;
use Database\Factories\NasDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * A MikroTik router acting as the hotspot NAS.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $site_id
 * @property string $name
 * @property string $nasname
 * @property string $shared_secret
 * @property int $coa_port
 * @property string|null $api_host
 * @property int $api_port
 * @property string|null $api_username
 * @property string|null $api_password
 * @property bool $api_uses_tls
 * @property string $status
 */
#[Hidden(['shared_secret', 'api_password'])]
class NasDevice extends Model
{
    /** @use HasFactory<NasDeviceFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'site_id',
        'name',
        'nasname',
        'shared_secret',
        'api_host',
        'api_port',
        'api_username',
        'api_password',
        'api_uses_tls',
        'coa_port',
        'model',
        'routeros_version',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            /*
             * FreeRADIUS needs the shared secret in cleartext and reads it from
             * the `nas` table, which only its restricted account can see. This
             * copy is encrypted so the console can display and re-sync it without
             * a second plaintext store.
             */
            'shared_secret' => 'encrypted',
            'api_password' => 'encrypted',
            'api_uses_tls' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Whether this router can be queried for its hotspot host table, which is
     * what lets a user pick a nearby TV to bind instead of typing a MAC.
     */
    public function hasApiCredentials(): bool
    {
        return filled($this->api_host) && filled($this->api_username);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Accounting NAS-IP-Address is often the router's LAN address, while nasname
     * is the public IP FreeRADIUS sees after NAT.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForRadiusIp(Builder $query, string $ip): Builder
    {
        return $query->where(function (Builder $inner) use ($ip): void {
            $inner->where('nasname', $ip)->orWhere('api_host', $ip);
        });
    }

    /**
     * @return Collection<string, self>
     */
    public static function indexedByRadiusIp(): Collection
    {
        $index = new Collection;

        foreach (static::withoutTenantScope()->get() as $device) {
            $index->put($device->nasname, $device);

            if (filled($device->api_host)) {
                $index->put($device->api_host, $device);
            }
        }

        return $index;
    }
}
