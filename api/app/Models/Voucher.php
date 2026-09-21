<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\BelongsToTenant;
use App\Domain\Voucher\VoucherCode;
use App\Domain\Voucher\VoucherStatus;
use Database\Factories\VoucherFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A single redeemable code.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $plan_id
 * @property int|null $batch_id
 * @property string $code
 * @property string $code_hash
 * @property string|null $code_suffix
 * @property VoucherStatus $status
 * @property int $validity_seconds
 * @property int|null $duration_seconds
 * @property int|null $data_cap_bytes
 * @property int|null $rate_limit_down_kbps
 * @property int|null $rate_limit_up_kbps
 * @property int $device_limit
 * @property int $price_minor
 * @property string|null $bound_mac
 * @property Carbon|null $first_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $shelf_expires_at
 * @property Carbon|null $printed_at
 */
#[Hidden(['code', 'code_hash'])]
class Voucher extends Model
{
    /** @use HasFactory<VoucherFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'batch_id',
        'code',
        'code_hash',
        'code_suffix',
        'status',
        'validity_seconds',
        'duration_seconds',
        'data_cap_bytes',
        'rate_limit_down_kbps',
        'rate_limit_up_kbps',
        'device_limit',
        'price_minor',
        'bound_mac',
        'first_used_at',
        'expires_at',
        'shelf_expires_at',
        'disabled_at',
        'disabled_by_user_id',
        'disable_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            /*
             * Encrypted so a dump of this table alone reveals nothing. The
             * cleartext does exist in radcheck, because MikroTik's hotspot uses
             * CHAP and the RADIUS server must know the password to answer a
             * challenge; that table is restricted to the radius account.
             */
            'code' => 'encrypted',
            'status' => VoucherStatus::class,
            'first_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'shelf_expires_at' => 'datetime',
            'printed_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<VoucherBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(VoucherBatch::class, 'batch_id');
    }

    /**
     * @return HasMany<VoucherDevice, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(VoucherDevice::class);
    }

    /**
     * @return HasOne<VoucherUsage, $this>
     */
    public function usage(): HasOne
    {
        return $this->hasOne(VoucherUsage::class);
    }

    /**
     * The code as it should appear on a printed card.
     */
    public function displayCode(): string
    {
        return VoucherCode::forDisplay($this->code, (int) config('kasi.voucher.group_size'));
    }

    /**
     * Whether this voucher can still authenticate a client right now.
     *
     * Checked in the portal for a helpful message. FreeRADIUS enforces the same
     * conditions independently, so a voucher is never usable merely because this
     * returned true.
     */
    public function isRedeemable(): bool
    {
        if (! $this->status->isUsable()) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->status === VoucherStatus::Unused
            && $this->shelf_expires_at !== null
            && $this->shelf_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Whether this code is already tied to a device.
     */
    public function isBound(): bool
    {
        return $this->bound_mac !== null;
    }

    /**
     * Scope to vouchers that have never been redeemed and are still sellable,
     * used when allocating stock to an online order.
     *
     * @param  Builder<Voucher>  $query
     * @return Builder<Voucher>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('status', VoucherStatus::Unused)
            ->whereNull('bound_mac')
            ->where(function (Builder $inner): void {
                $inner->whereNull('shelf_expires_at')
                    ->orWhere('shelf_expires_at', '>', now());
            });
    }
}
