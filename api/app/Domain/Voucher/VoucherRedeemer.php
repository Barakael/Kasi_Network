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
        $this->assertNotLocked($context);

        $code = VoucherCode::normalise($rawCode);

        if (! VoucherCode::isPlausible($code)) {
            $this->recordFailure($context);
        }

        $voucher = Voucher::query()
            ->where('code_hash', $this->hasher->hash($code))
            ->first();

        if ($voucher === null || ! $voucher->isRedeemable()) {
            $this->recordFailure($context);
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

        $this->activateOnFirstUse($voucher);

        return $voucher->fresh('plan');
    }

    /**
     * Starts the validity window the moment the customer actually uses the code.
     *
     * Issuance only provisions RADIUS credentials; the clock and status flip
     * here (or in FreeRADIUS post-auth if the router authenticates first).
     * Quotas (online time / data) still deplete only through accounting.
     */
    private function activateOnFirstUse(Voucher $voucher): void
    {
        if ($voucher->first_used_at !== null) {
            return;
        }

        $now = now();
        $expiresAt = $now->copy()->addSeconds($voucher->validity_seconds);

        $voucher->update([
            'first_used_at' => $now,
            'expires_at' => $expiresAt,
            'status' => VoucherStatus::Active,
        ]);

        $this->provisioner->stampExpiration($voucher->fresh());
    }

    private function assertNotLocked(PortalContext $context): void
    {
        $max = (int) config('kasi.voucher.redeem_attempts');

        if (RateLimiter::tooManyAttempts($this->throttleKey($context), $max)) {
            throw ValidationException::withMessages([
                'code' => 'Umezuiwa kwa dakika 5. Jaribu tena baadaye.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function recordFailure(PortalContext $context): never
    {
        $key = $this->throttleKey($context);
        $max = (int) config('kasi.voucher.redeem_attempts');
        $lockSeconds = (int) config('kasi.voucher.redeem_lock_seconds');

        RateLimiter::hit($key, $lockSeconds);

        $remaining = max(0, $max - RateLimiter::attempts($key));

        if ($remaining === 0) {
            throw ValidationException::withMessages([
                'code' => 'Umezuiwa kwa dakika 5. Jaribu tena baadaye.',
            ]);
        }

        $message = $remaining === 1
            ? 'Umekosea vocha. Umebakiza jaribio 1.'
            : "Umekosea vocha. Umebakiza majaribio {$remaining}.";

        throw ValidationException::withMessages([
            'code' => $message,
        ]);
    }

    private function throttleKey(PortalContext $context): string
    {
        return 'redeem:'.($context->clientMac ?? $context->clientIp ?? 'unknown');
    }
}
