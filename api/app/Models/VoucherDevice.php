<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\BelongsToTenant;
use Database\Factories\VoucherDeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A device authorised by MAC address against a voucher.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $voucher_id
 * @property string $mac
 * @property string|null $label
 * @property string|null $vendor
 * @property string $status
 * @property string $created_via
 */
class VoucherDevice extends Model
{
    /** @use HasFactory<VoucherDeviceFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'voucher_id',
        'mac',
        'label',
        'vendor',
        'status',
        'created_via',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
