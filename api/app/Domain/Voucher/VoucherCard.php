<?php

declare(strict_types=1);

namespace App\Domain\Voucher;

use App\Models\Site;
use App\Models\Tenant;
use App\Models\Voucher;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * One voucher rendered as the card a customer is handed.
 *
 * Assembled here rather than in the Blade template because the QR payload and the
 * human-readable terms both need deriving, and a template is the wrong place to
 * work out that 3600 seconds should read "1 hour".
 */
final readonly class VoucherCard
{
    private function __construct(
        public string $code,
        public string $displayCode,
        public string $qrSvg,
        public string $planName,
        public string $terms,
        public ?string $ssid,
        public ?string $expiryNote,
    ) {}

    public static function for(Voucher $voucher, Tenant $tenant, ?Site $site = null): self
    {
        $code = $voucher->code;

        return new self(
            code: $code,
            displayCode: $voucher->displayCode(),
            qrSvg: self::qrSvg(self::qrPayload($code)),
            planName: $voucher->plan->name,
            terms: self::terms($voucher),
            ssid: $site?->ssid ?? $tenant->name,
            expiryNote: self::expiryNote($voucher),
        );
    }

    /**
     * The link the QR code carries.
     *
     * Deep-links into the portal with the code in the path. The portal auto-redeems
     * on load when the hotspot session (site + optional CHAP params) is present,
     * so a customer who can scan never types. The printed characters remain the
     * fallback when the camera is blocked by the walled garden.
     */
    public static function redemptionUrl(string $code, ?Site $site = null): string
    {
        $url = config('kasi.portal_url').'/r/'.VoucherCode::normalise($code);

        if ($site?->nas_identifier) {
            return $url.'?site='.rawurlencode($site->nas_identifier);
        }

        return $url;
    }

    /**
     * What is actually drawn into the QR modules.
     *
     * The printed square is ~20 mm. A full portal URL is a version-5+ code whose
     * modules are too small for a phone camera to lock onto. The normalised
     * voucher code is ten characters and fits a version-1 QR, which is what the
     * in-portal scanner and cheap cameras can read. Phone camera apps that are
     * not on the hotspot still show the code as text to type.
     */
    public static function qrPayload(string $code): string
    {
        return VoucherCode::normalise($code);
    }

    /**
     * A QR code as inline SVG.
     *
     * SVG rather than a raster image so the print stays sharp at any card size,
     * and inline so the browser needs no extra requests -- a sheet of 24 cards
     * would otherwise be 24 image fetches, and a print dialog that renders before
     * they finish produces cards with holes where the codes should be.
     */
    private static function qrSvg(string $payload): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle(size: 160, margin: 4),
            new SvgImageBackEnd,
        ));

        /*
         * Medium error correction rather than the default L. Cards get creased,
         * thumbed and rained on, and the payload is short enough that the extra
         * redundancy costs nothing in module count.
         *
         * Strip the XML declaration: Bacon emits <?xml …?> which, inlined in
         * HTML, makes some browsers treat the SVG as a broken image instead of
         * a drawable QR.
         */
        $svg = $writer->writeString($payload, ecLevel: ErrorCorrectionLevel::M());

        return (string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
    }

    /**
     * The bundle in one line, as it should read on a card.
     */
    private static function terms(Voucher $voucher): string
    {
        $parts = [];

        if ($voucher->duration_seconds !== null) {
            $parts[] = self::humanDuration($voucher->duration_seconds).' online';
        } else {
            $parts[] = self::humanDuration($voucher->validity_seconds);
        }

        if ($voucher->data_cap_bytes !== null) {
            $parts[] = self::humanBytes($voucher->data_cap_bytes);
        }

        if ($voucher->rate_limit_down_kbps !== null) {
            $parts[] = self::humanSpeed($voucher->rate_limit_down_kbps);
        }

        if ($voucher->device_limit > 1) {
            $parts[] = $voucher->device_limit.' devices';
        }

        return implode(' · ', $parts);
    }

    /**
     * The two deadlines a customer needs to know about, in plain words.
     *
     * Printing both matters: "use by" is when the unsold card stops working, and
     * the validity window is how long they have once they start. Customers who
     * only see one assume the other does not exist.
     */
    private static function expiryNote(Voucher $voucher): ?string
    {
        $notes = [];

        if ($voucher->shelf_expires_at !== null) {
            $notes[] = 'Use by '.$voucher->shelf_expires_at->format('j M Y');
        }

        // Always tell the customer the clock starts when they connect, not when
        // the card was printed.
        $notes[] = 'Starts on first use · '.self::humanDuration($voucher->validity_seconds).' window';

        return implode(' · ', $notes);
    }

    private static function humanDuration(int $seconds): string
    {
        return match (true) {
            $seconds % 2592000 === 0 => self::plural(intdiv($seconds, 2592000), 'month'),
            $seconds % 604800 === 0 => self::plural(intdiv($seconds, 604800), 'week'),
            $seconds % 86400 === 0 => self::plural(intdiv($seconds, 86400), 'day'),
            $seconds % 3600 === 0 => self::plural(intdiv($seconds, 3600), 'hour'),
            default => self::plural(max(1, intdiv($seconds, 60)), 'minute'),
        };
    }

    /**
     * Data caps in the units they were sold in.
     *
     * Decimal rather than binary: a bundle advertised as 5 GB is 5,000,000,000
     * bytes on every price list in the region, and rendering it as "4.66 GB"
     * generates support calls.
     */
    private static function humanBytes(int $bytes): string
    {
        foreach ([1_000_000_000 => 'GB', 1_000_000 => 'MB'] as $unit => $suffix) {
            if ($bytes >= $unit) {
                $value = $bytes / $unit;

                return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').' '.$suffix;
            }
        }

        return $bytes.' B';
    }

    private static function humanSpeed(int $kbps): string
    {
        return $kbps >= 1000
            ? rtrim(rtrim(number_format($kbps / 1000, 1, '.', ''), '0'), '.').' Mbps'
            : $kbps.' Kbps';
    }

    private static function plural(int $count, string $noun): string
    {
        return $count.' '.$noun.($count === 1 ? '' : 's');
    }
}
