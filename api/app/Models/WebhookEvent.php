<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WebhookEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A received Snippe webhook, recorded before it is acted on.
 *
 * The unique index on event_id is the idempotency guarantee: Snippe retries a
 * failed delivery up to five times and documents that the same event may arrive
 * more than once, so without it a single payment could issue several vouchers.
 *
 * Not tenant-scoped. The tenant is resolved from the webhook URL, and a request
 * that fails verification may have no resolvable tenant at all but still needs to
 * be recorded.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $event_id
 * @property string $event_type
 * @property string|null $reference
 * @property array<string, mixed> $payload
 * @property bool $signature_verified
 * @property Carbon|null $processed_at
 */
class WebhookEvent extends Model
{
    /** @use HasFactory<WebhookEventFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'event_type',
        'reference',
        'payload',
        'signature_verified',
        'processed_at',
        'processing_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'signature_verified' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }
}
