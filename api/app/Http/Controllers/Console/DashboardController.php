<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Billing\CollectionBook;
use App\Domain\Billing\RevenueBook;
use App\Domain\Customers\CustomerDirectory;
use App\Models\Customer;
use App\Models\NasDevice;
use App\Models\RadAcct;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use Illuminate\Http\JsonResponse;

class DashboardController
{
    public function __invoke(RevenueBook $revenue, CollectionBook $collections, CustomerDirectory $directory): JsonResponse
    {
        $usernames = VoucherUsage::query()->pluck('username');

        $concurrent = $usernames->isEmpty()
            ? 0
            : RadAcct::query()->open()->whereIn('username', $usernames)->count();

        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();
        $now = now();
        $split = $collections->totals($today, $now);
        $week = $collections->totals(now()->startOfWeek(), $now);
        $previousWeek = $collections->totals(now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek());

        $devices = NasDevice::query()->where('status', 'active')->get();
        $quiet = $devices->contains(fn (NasDevice $device) => $device->isQuiet());
        $lastRadius = $devices
            ->map(fn (NasDevice $device) => $device->lastRadiusAt())
            ->filter()
            ->sort()
            ->last();

        $unusedCards = (int) Voucher::query()->available()->whereNotNull('batch_id')->count();
        $haiCount = $directory->haiIds()->count();
        $customerTotal = Customer::query()->count();

        return response()->json([
            'concurrent_sessions' => $concurrent,
            'revenue_today_minor' => $revenue->totalBetween($today, $now),
            'revenue_month_minor' => $revenue->totalBetween($monthStart, $now),
            'vouchers_activated_today' => $revenue->usedVoucherCountBetween($today, $now),
            'revenue_series' => $revenue->seriesBetween(now()->subDays(13)->startOfDay(), $now),
            'lipia_today_minor' => $split['lipia'],
            'kadi_today_minor' => $split['kadi'],
            'unused_cards' => $unusedCards,
            'router_quiet' => $devices->isEmpty() ? true : $quiet,
            'last_radius_at' => $lastRadius?->toIso8601String(),
            'week_minor' => $week['lipia'] + $week['kadi'],
            'previous_week_minor' => $previousWeek['lipia'] + $previousWeek['kadi'],
            'week_lipia_minor' => $week['lipia'],
            'week_kadi_minor' => $week['kadi'],
            'hai_count' => $haiCount,
            'kimya_count' => max(0, $customerTotal - $haiCount),
        ]);
    }
}
