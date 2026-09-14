<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Billing\OrderStatus;
use App\Models\Order;
use App\Models\RadAcct;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController
{
    public function __invoke(): JsonResponse
    {
        $usernames = VoucherUsage::query()->pluck('username');

        $concurrent = $usernames->isEmpty()
            ? 0
            : RadAcct::query()->open()->whereIn('username', $usernames)->count();

        $today = now()->startOfDay();

        $revenueToday = (int) Order::query()
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Fulfilled])
            ->where('paid_at', '>=', $today)
            ->sum(DB::raw('COALESCE(net_minor, amount_minor)'));

        $revenueMonth = (int) Order::query()
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Fulfilled])
            ->where('paid_at', '>=', now()->startOfMonth())
            ->sum(DB::raw('COALESCE(net_minor, amount_minor)'));

        $vouchersToday = Voucher::query()
            ->where('first_used_at', '>=', $today)
            ->count();

        $series = Order::query()
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Fulfilled])
            ->where('paid_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(paid_at) as day, SUM(COALESCE(net_minor, amount_minor)) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return response()->json([
            'concurrent_sessions' => $concurrent,
            'revenue_today_minor' => $revenueToday,
            'revenue_month_minor' => $revenueMonth,
            'vouchers_activated_today' => $vouchersToday,
            'revenue_series' => $series,
        ]);
    }
}
