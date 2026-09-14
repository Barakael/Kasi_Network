<?php

declare(strict_types=1);

namespace App\Domain\Radius;

use App\Domain\Voucher\QuotaAction;
use App\Domain\Voucher\VoucherStatus;
use App\Models\NasDevice;
use App\Models\Plan;
use App\Models\RadAcct;
use App\Models\Voucher;
use App\Models\VoucherUsage;

/**
 * Cuts or throttles sessions that have already overrun their bundle.
 *
 * Auth-time sqlcounter cannot affect a session in flight. This sweep runs every
 * minute over open radacct rows and dispatches CoA. Overrun is bounded by the
 * interim-accounting interval (2 or 5 minutes) plus this period.
 */
final readonly class QuotaEnforcer
{
    public function __construct(private CoaDispatcher $coa) {}

    public function enforce(): int
    {
        $acted = 0;
        $devices = NasDevice::withoutTenantScope()
            ->get()
            ->keyBy('nasname');

        RadAcct::query()
            ->open()
            ->orderBy('radacctid')
            ->chunkById(200, function ($sessions) use ($devices, &$acted): void {
                $usernames = $sessions->pluck('username')->unique()->all();

                $usages = VoucherUsage::withoutTenantScope()
                    ->with('voucher')
                    ->whereIn('username', $usernames)
                    ->get()
                    ->keyBy('username');

                foreach ($sessions as $session) {
                    $usage = $usages->get($session->username);
                    $voucher = $usage?->voucher;

                    if (! $voucher instanceof Voucher || ! $voucher->status->isUsable()) {
                        $nas = $devices->get($session->nasipaddress);

                        if ($nas instanceof NasDevice) {
                            $this->coa->disconnect($session, $nas, 'voucher-unusable');
                            $acted++;
                        }

                        continue;
                    }

                    if ($this->isOverQuota($voucher, $usage, $session)) {
                        $acted += $this->act($voucher, $session, $devices->get($session->nasipaddress)) ? 1 : 0;
                    }
                }
            }, column: 'radacctid');

        return $acted;
    }

    private function isOverQuota(Voucher $voucher, VoucherUsage $usage, RadAcct $session): bool
    {
        if ($voucher->expires_at !== null && $voucher->expires_at->isPast()) {
            $voucher->update(['status' => VoucherStatus::Expired]);

            return true;
        }

        $seconds = $usage->seconds_used + $session->elapsedSeconds();

        if ($voucher->duration_seconds !== null && $seconds >= $voucher->duration_seconds) {
            return true;
        }

        $bytes = $usage->bytesTotal() + $session->bytesTotal();

        return $voucher->data_cap_bytes !== null && $bytes >= $voucher->data_cap_bytes;
    }

    private function act(Voucher $voucher, RadAcct $session, ?NasDevice $nas): bool
    {
        if (! $nas instanceof NasDevice) {
            return false;
        }

        $plan = Plan::withoutTenantScope()->find($voucher->plan_id);

        if ($plan?->on_quota_exhausted === QuotaAction::Throttle) {
            $rate = $plan->throttleRateLimitAttribute();

            if ($rate !== null) {
                return $this->coa->throttle($session, $nas, $rate);
            }
        }

        $voucher->update(['status' => VoucherStatus::Exhausted]);

        return $this->coa->disconnect($session, $nas, 'quota-exhausted');
    }
}
