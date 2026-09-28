<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Billing\TanzanianPhone;
use App\Domain\Tenancy\BelongsToTenant;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A buyer of this operator's Wi‑Fi, identified by phone.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $site_id
 * @property string $phone
 * @property string|null $last_mac
 * @property Carbon|null $first_seen_at
 * @property Carbon|null $last_seen_at
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'site_id',
        'phone',
        'last_mac',
        'first_seen_at',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
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
     * @return HasMany<Voucher, $this>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'phone', 'phone');
    }

    public function maskedPhone(): string
    {
        return TanzanianPhone::mask($this->phone);
    }

    public function localPhone(): string
    {
        return TanzanianPhone::toLocal($this->phone);
    }
}
