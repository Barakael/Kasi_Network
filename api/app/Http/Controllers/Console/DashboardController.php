<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Billing\RevenueBook;
use App\Models\RadAcct;
use App\Models\VoucherUsage;
use Illuminate\Http\JsonResponse;

class DashboardController
{
    public function __invoke(RevenueBook $revenue): JsonResponse
    {
        $usernames = VoucherUsage::query()->pluck('username');

        $concurrent = $usernames->isEmpty()
            ? 0
            : RadAcct::query()->open()->whereIn('username', $usernames)->count();

        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();
        $now = now();

        return response()->json([
            'concurrent_sessions' => $concurrent,
            'revenue_today_minor' => $revenue->totalBetween($today, $now),
            'revenue_month_minor' => $revenue->totalBetween($monthStart, $now),
            'vouchers_activated_today' => $revenue->usedVoucherCountBetween($today, $now),
            'revenue_series' => $revenue->seriesBetween(now()->subDays(13)->startOfDay(), $now),
        ]);
    }
}
