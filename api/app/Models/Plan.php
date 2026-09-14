<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\BelongsToTenant;
use App\Domain\Voucher\BillingPeriod;
use App\Domain\Voucher\QuotaAction;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A sellable bundle.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property BillingPeriod $billing_period
 * @property int $validity_seconds
 * @property int|null $duration_seconds
 * @property int|null $data_cap_bytes
 * @property int $price_minor
 * @property int|null $rate_limit_down_kbps
 * @property int|null $rate_limit_up_kbps
 * @property int $device_limit
 * @property QuotaAction $on_quota_exhausted
 * @property int|null $throttle_down_kbps
 * @property int|null $throttle_up_kbps
 * @property int|null $shelf_life_days
 * @property bool $is_active
 * @property bool $is_sold_online
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'billing_period',
        'validity_seconds',
        'duration_seconds',
        'data_cap_bytes',
        'price_minor',
        'rate_limit_down_kbps',
        'rate_limit_up_kbps',
        'device_limit',
        'on_quota_exhausted',
        'throttle_down_kbps',
        'throttle_up_kbps',
        'shelf_life_days',
        'is_active',
        'is_sold_online',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_period' => BillingPeriod::class,
            'on_quota_exhausted' => QuotaAction::class,
            'is_active' => 'boolean',
            'is_sold_online' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Voucher, $this>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    /**
     * @return HasMany<VoucherBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(VoucherBatch::class);
    }

    /**
     * The Mikrotik-Rate-Limit value for this bundle, or null to leave the
     * router's own queue defaults in place.
     */
    public function rateLimitAttribute(): ?string
    {
        return self::formatRateLimit($this->rate_limit_up_kbps, $this->rate_limit_down_kbps);
    }

    /**
     * The reduced rate applied once a data cap is spent, when the plan throttles
     * rather than disconnects.
     */
    public function throttleRateLimitAttribute(): ?string
    {
        return self::formatRateLimit($this->throttle_up_kbps, $this->throttle_down_kbps);
    }

    /**
     * Mikrotik-Rate-Limit is "upload/download" from the client's perspective,
     * which is the reverse of how operators usually quote a package. Getting the
     * order wrong silently sells the wrong product, so the conversion lives here.
     */
    public static function formatRateLimit(?int $upKbps, ?int $downKbps): ?string
    {
        if ($upKbps === null && $downKbps === null) {
            return null;
        }

        return sprintf('%dk/%dk', $upKbps ?? $downKbps, $downKbps ?? $upKbps);
    }

    /**
     * The terms copied onto each voucher at issue time, so later edits to this
     * plan cannot change what an already-sold voucher is worth.
     *
     * @return array{validity_seconds: int, duration_seconds: int|null, data_cap_bytes: int|null, rate_limit_down_kbps: int|null, rate_limit_up_kbps: int|null, device_limit: int, price_minor: int}
     */
    public function termsSnapshot(): array
    {
        return [
            'validity_seconds' => $this->validity_seconds,
            'duration_seconds' => $this->duration_seconds,
            'data_cap_bytes' => $this->data_cap_bytes,
            'rate_limit_down_kbps' => $this->rate_limit_down_kbps,
            'rate_limit_up_kbps' => $this->rate_limit_up_kbps,
            'device_limit' => $this->device_limit,
            'price_minor' => $this->price_minor,
        ];
    }
}
