<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\BelongsToTenant;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A physical location with one or more hotspot routers.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $slug
 * @property string|null $ssid
 * @property string $nas_identifier
 * @property string $status
 */
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'ssid',
        'nas_identifier',
        'timezone',
        'status',
        'address',
    ];

    /**
     * @return HasMany<NasDevice, $this>
     */
    public function nasDevices(): HasMany
    {
        return $this->hasMany(NasDevice::class);
    }

    /**
     * @return HasMany<VoucherBatch, $this>
     */
    public function voucherBatches(): HasMany
    {
        return $this->hasMany(VoucherBatch::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
