<?php

declare(strict_types=1);

namespace App\Domain\Voucher;

use App\Domain\Radius\RadiusProvisioner;
use App\Domain\Router\OuiLookup;
use App\Domain\Support\MacAddress;
use App\Models\Voucher;
use App\Models\VoucherDevice;
use Illuminate\Validation\ValidationException;

/**
 * Binds a MAC to a voucher so a browserless device can authenticate silently.
 *
 * The MAC is not written as a radcheck username: that namespace is global, and
 * two operators binding the same TV would collide. FreeRADIUS rewrites the MAC
 * to this voucher's code, scoped by the NAS the request arrived on.
 */
final readonly class MacBinder
{
    public function __construct(
        private RadiusProvisioner $provisioner,
        private OuiLookup $oui,
    ) {}

    public function bind(Voucher $voucher, string $rawMac, string $via = 'portal', ?string $label = null): VoucherDevice
    {
        if (! $voucher->isRedeemable() && $voucher->status !== VoucherStatus::Active) {
            throw ValidationException::withMessages([
                'mac' => 'This voucher cannot accept another device.',
            ]);
        }

        $mac = MacAddress::parse($rawMac)->toString();

        $active = $voucher->devices()->where('status', 'active')->count();

        if ($voucher->bound_mac && $voucher->bound_mac !== $mac) {
            $active++;
        }

        if ($active >= $voucher->device_limit) {
            throw ValidationException::withMessages([
                'mac' => 'This voucher has no remaining device slots.',
            ]);
        }

        $device = VoucherDevice::query()->updateOrCreate(
            [
                'tenant_id' => $voucher->tenant_id,
                'mac' => $mac,
            ],
            [
                'voucher_id' => $voucher->id,
                'label' => $label,
                'vendor' => $this->oui->vendor($mac),
                'status' => 'active',
                'created_via' => $via,
                'last_seen_at' => now(),
            ],
        );

        if ($voucher->bound_mac === null) {
            $voucher->update(['bound_mac' => $mac]);
        }

        /*
         * A one-device bundle can lock at RADIUS with Calling-Station-Id. A
         * shared bundle must not: the second TV would then be refused even
         * though voucher_devices has a spare slot. Those authentications arrive
         * as MAC usernames and are rewritten onto this voucher instead.
         */
        if ($voucher->device_limit === 1) {
            $this->provisioner->bindCallingStation($voucher, $mac);
        }

        return $device;
    }

    public function revoke(VoucherDevice $device): void
    {
        $device->update(['status' => 'revoked']);
    }

    /**
     * Frees every device slot on a voucher so a different phone can redeem it.
     */
    public function release(Voucher $voucher): void
    {
        $voucher->devices()->where('status', 'active')->update(['status' => 'revoked']);
        $voucher->update(['bound_mac' => null]);
        $this->provisioner->unbindCallingStation($voucher->code);
    }
}
