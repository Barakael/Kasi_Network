<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Domain\Radius\RadiusProvisioner;
use App\Domain\Support\MacAddress;
use App\Domain\Voucher\VoucherIssuer;
use App\Models\Order;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

/**
 * Turns a paid order into a live voucher.
 *
 * Online sales issue a fresh code rather than taking one from a printed batch:
 * mixing the two would sell a card that is also sitting in a kiosk drawer.
 */
final readonly class OrderFulfiller
{
    public function __construct(
        private VoucherIssuer $issuer,
        private RadiusProvisioner $provisioner,
    ) {}

    public function fulfill(Order $order): Order
    {
        if ($order->status === OrderStatus::Fulfilled && $order->voucher_id !== null) {
            return $order;
        }

        return DB::transaction(function () use ($order): Order {
            $order = Order::withoutTenantScope()->lockForUpdate()->findOrFail($order->id);

            if ($order->status === OrderStatus::Fulfilled && $order->voucher_id !== null) {
                return $order;
            }

            $voucher = $order->voucher_id
                ? Voucher::withoutTenantScope()->findOrFail($order->voucher_id)
                : $this->issuer->issueOne($order->plan);

            if ($order->client_mac) {
                $mac = MacAddress::parse($order->client_mac)->toString();
                $voucher->update(['bound_mac' => $mac]);
                $this->provisioner->bindCallingStation($voucher, $mac);
            }

            $order->update([
                'voucher_id' => $voucher->id,
                'status' => OrderStatus::Fulfilled,
                'fulfilled_at' => now(),
            ]);

            return $order->fresh(['voucher', 'plan']);
        });
    }

    /**
     * Releases a reserved voucher when payment fails, expires or is voided.
     */
    public function release(Order $order): void
    {
        if ($order->voucher_id === null || $order->status === OrderStatus::Fulfilled) {
            return;
        }

        $order->update(['voucher_id' => null]);
    }
}
