<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Voucher\VoucherCode;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A hotspot operator.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string $code_prefix
 * @property string $status
 * @property string $currency
 * @property string $timezone
 * @property string|null $snippe_api_key
 * @property string|null $snippe_webhook_secret
 */
#[Hidden(['snippe_api_key', 'snippe_webhook_secret'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'code_prefix',
        'status',
        'currency',
        'timezone',
        'snippe_api_key',
        'snippe_webhook_secret',
        'portal_name',
        'logo_path',
        'primary_color',
        'support_phone',
    ];

    /**
     * The model's own key stays an auto-incrementing integer; only the public
     * uuid column is generated.
     */
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
            /*
             * Payment credentials never appear in a query result, a log line or
             * an API response in readable form. Note that these are the operator's
             * live mobile money keys: a leak here lets an attacker take payments
             * in their name.
             */
            'snippe_api_key' => 'encrypted',
            'snippe_webhook_secret' => 'encrypted',
        ];
    }

    /**
     * @return HasMany<Site, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * @return HasMany<NasDevice, $this>
     */
    public function nasDevices(): HasMany
    {
        return $this->hasMany(NasDevice::class);
    }

    /**
     * @return HasMany<Plan, $this>
     */
    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Whether this operator can take mobile money payments yet. Printed vouchers
     * work without Snippe credentials; online sales do not.
     */
    public function acceptsOnlinePayments(): bool
    {
        return filled($this->snippe_api_key) && filled($this->snippe_webhook_secret);
    }

    /**
     * Proposes a voucher code prefix from an operator's name.
     *
     * Restricted to the Crockford alphabet: a prefix containing I, L or O would
     * be folded onto 1, 1 and 0 when a redeemed code is normalised, producing
     * codes that never match what was printed on the card.
     */
    public static function suggestCodePrefix(string $name): string
    {
        $safe = (string) preg_replace(
            '/[^'.VoucherCode::ALPHABET.']/',
            '',
            Str::upper($name),
        );

        return $safe === '' ? 'KAS' : Str::substr($safe, 0, 3);
    }
}
