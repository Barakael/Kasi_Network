<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Billing\OrderStatus;
use App\Domain\Tenancy\BelongsToTenant;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A mobile money purchase of a bundle from the captive portal.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $plan_id
 * @property int|null $voucher_id
 * @property OrderStatus $status
 * @property int $amount_minor
 * @property string $currency
 * @property string $phone
 * @property string|null $client_mac
 * @property string|null $snippe_reference
 * @property Carbon|null $paid_at
 * @property Carbon|null $expires_at
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'site_id',
        'plan_id',
        'nas_device_id',
        'voucher_id',
        'status',
        'amount_minor',
        'currency',
        'phone',
        'customer_name',
        'customer_email',
        'client_mac',
        'client_ip',
        'snippe_reference',
        'snippe_external_reference',
        'channel_provider',
        'fees_minor',
        'net_minor',
        'failure_reason',
        'paid_at',
        'fulfilled_at',
        'expires_at',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getIncrementing(): bool
    {
        return true;
    }

    public function getKeyType(): string
    {
        return 'int';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'paid_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'expires_at' => 'datetime',
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
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<NasDevice, $this>
     */
    public function nasDevice(): BelongsTo
    {
        return $this->belongsTo(NasDevice::class);
    }

    /**
     * The Idempotency-Key sent to Snippe.
     *
     * Snippe rejects keys longer than 30 characters with a PAY_001 error, and a
     * canonical UUID is 36, so the dashes are stripped to land at 32... which is
     * still too long. The first 30 hex characters of the uuid are used, which
     * remains unique in practice for the lifetime of an order.
     */
    public function idempotencyKey(): string
    {
        return substr(str_replace('-', '', $this->uuid), 0, 30);
    }

    /**
     * Whether the portal should stop polling.
     */
    public function isSettled(): bool
    {
        return $this->status->isSettled();
    }

    public static function newUniqueReference(): string
    {
        return (string) Str::uuid();
    }
}
