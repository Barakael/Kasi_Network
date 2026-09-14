<?php

declare(strict_types=1);

namespace App\Domain\Voucher;

use App\Domain\Radius\RadiusProvisioner;
use App\Domain\Support\MacAddress;
use App\Domain\Tenancy\PortalContext;
use App\Models\Voucher;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Redeems a printed or purchased code at the captive portal.
 *
 * FreeRADIUS is still the authority: this check is for a clear error message and
 * to bind the MAC before the CHAP login hits the router. A yes here never grants
 * access by itself.
 */
final readonly class VoucherRedeemer
{
    public function __construct(
        private VoucherCodeHasher $hasher,
        private RadiusProvisioner $provisioner,
    ) {}

    public function redeem(string $rawCode, PortalContext $context): Voucher
    {
        $code = VoucherCode::normalise($rawCode);

        if (! VoucherCode::hasValidCheckCharacter($code)) {
            throw ValidationException::withMessages([
                'code' => 'That code looks mistyped. Check the letters and try again.',
            ]);
        }

        $this->throttle($context);

        $voucher = Voucher::query()
            ->where('code_hash', $this->hasher->hash($code))
            ->first();

        if ($voucher === null || ! $voucher->isRedeemable()) {
            RateLimiter::hit($this->throttleKey($context));

            throw ValidationException::withMessages([
                'code' => 'This voucher cannot be used.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($context));

        if ($context->clientMac !== null) {
            $mac = MacAddress::parse($context->clientMac)->toString();

            if ($voucher->bound_mac !== null && $voucher->bound_mac !== $mac) {
                throw ValidationException::withMessages([
                    'code' => 'This voucher is already in use on another device.',
                ]);
            }

            if ($voucher->bound_mac === null && $voucher->device_limit === 1) {
                $voucher->update(['bound_mac' => $mac]);
                $this->provisioner->bindCallingStation($voucher, $mac);
            }
        }

        return $voucher->fresh('plan');
    }

    private function throttle(PortalContext $context): void
    {
        $max = (int) config('kasi.voucher.redeem_attempts_per_minute');

        if (RateLimiter::tooManyAttempts($this->throttleKey($context), $max)) {
            throw ValidationException::withMessages([
                'code' => 'Too many attempts. Wait a minute and try again.',
            ]);
        }
    }

    private function throttleKey(PortalContext $context): string
    {
        return 'redeem:'.($context->clientMac ?? $context->clientIp ?? 'unknown');
    }
}
