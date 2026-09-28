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
use Illuminate\Support\Facades\Storage;
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
 * @property string|null $palmpesa_api_token
 * @property string|null $palmpesa_user_id
 * @property string|null $palmpesa_vendor
 */
#[Hidden(['snippe_api_key', 'snippe_webhook_secret', 'palmpesa_api_token'])]
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
        'palmpesa_api_token',
        'palmpesa_user_id',
        'palmpesa_vendor',
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
            'palmpesa_api_token' => 'encrypted',
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
     * Public URL for the operator logo, or null when none is on file.
     */
    public function logoUrl(): ?string
    {
        if (! filled($this->logo_path)) {
            return null;
        }

        return url('/api/portal/tenants/'.$this->uuid.'/logo');
    }

    /**
     * Inline data URI so a print sheet does not fetch the logo over the kiosk link.
     */
    public function logoDataUri(): ?string
    {
        if (! filled($this->logo_path) || ! Storage::disk('public')->exists($this->logo_path)) {
            return null;
        }

        $bytes = Storage::disk('public')->get($this->logo_path);
        $mime = Storage::disk('public')->mimeType($this->logo_path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    public function palmpesaConfigured(): bool
    {
        return $this->palmpesaApiToken() !== null;
    }

    /**
     * Bearer token used for PalmPesa USSD initiate. Tenant row wins; env is the
     * fallback for a single-operator VPS.
     */
    public function palmpesaApiToken(): ?string
    {
        if ($this->offsetExists('palmpesa_api_token')) {
            $stored = $this->getAttribute('palmpesa_api_token');

            if (filled($stored)) {
                return $stored;
            }
        }

        $fallback = (string) config('kasi.palmpesa.api_token');

        return $fallback !== '' ? $fallback : null;
    }

    /**
     * Whether this operator can take mobile money payments yet. Printed vouchers
     * work without a gateway; online sales need PalmPesa or Snippe credentials.
     */
    public function acceptsOnlinePayments(): bool
    {
        if ($this->palmpesaApiToken() !== null) {
            return true;
        }

        if (! $this->offsetExists('snippe_api_key') || ! $this->offsetExists('snippe_webhook_secret')) {
            return false;
        }

        return filled($this->getAttribute('snippe_api_key')) && filled($this->getAttribute('snippe_webhook_secret'));
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
