<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\BelongsToTenant;
use Database\Factories\VoucherUsageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rolled-up consumption for one voucher.
 *
 * Read directly by the FreeRADIUS sqlcounter modules, which is why the table name
 * is fixed and the username column mirrors radcheck.username. Changing either
 * requires a matching change in docker/freeradius/config.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $voucher_id
 * @property string $username
 * @property int $seconds_used
 * @property int $bytes_in
 * @property int $bytes_out
 * @property int $session_count
 */
class VoucherUsage extends Model
{
    /** @use HasFactory<VoucherUsageFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'voucher_usage';

    protected $fillable = [
        'tenant_id',
        'voucher_id',
        'username',
        'seconds_used',
        'bytes_in',
        'bytes_out',
        'session_count',
        'last_session_at',
        'rolled_up_through',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seconds_used' => 'integer',
            'bytes_in' => 'integer',
            'bytes_out' => 'integer',
            'session_count' => 'integer',
            'last_session_at' => 'datetime',
            'rolled_up_through' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function bytesTotal(): int
    {
        return $this->bytes_in + $this->bytes_out;
    }
}
