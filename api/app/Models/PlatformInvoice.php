<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PlatformInvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What a System Administrator owes Kasi for using the platform.
 *
 * Not mixed into the operator's hotspot Collections.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $amount_minor
 * @property string $currency
 * @property string $status
 * @property string|null $period_label
 * @property Carbon|null $due_at
 * @property Carbon|null $paid_at
 */
class PlatformInvoice extends Model
{
    /** @use HasFactory<PlatformInvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'amount_minor',
        'currency',
        'status',
        'period_label',
        'period_start',
        'period_end',
        'due_at',
        'paid_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
