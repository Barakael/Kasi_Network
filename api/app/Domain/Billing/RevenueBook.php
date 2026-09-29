<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Models\Order;
use App\Models\Voucher;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Operator take for the console. Printed cards count when first used; mobile
 * money counts when paid. A voucher sold online is not added again on redeem.
 */
final readonly class RevenueBook
{
    public function totalBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        return $this->voucherSales($from, $to) + $this->orderSales($from, $to);
    }

    public function usedVoucherCountBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        return (int) Voucher::query()
            ->whereNotNull('first_used_at')
            ->whereBetween('first_used_at', [$from, $to])
            ->count();
    }

    /**
     * @return Collection<int, array{day: string, orders: int, total: int}>
     */
    public function seriesBetween(DateTimeInterface $from, DateTimeInterface $to): Collection
    {
        $vouchers = Voucher::query()
            ->whereNotNull('first_used_at')
            ->whereBetween('first_used_at', [$from, $to])
            ->whereNotIn('id', $this->orderedVoucherIds())
            ->selectRaw('DATE(first_used_at) as day, COUNT(*) as sales, SUM(price_minor) as total')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $orders = Order::query()
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Fulfilled])
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('DATE(paid_at) as day, COUNT(*) as sales, SUM(COALESCE(net_minor, amount_minor)) as total')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $days = collect();
        $cursor = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        while ($cursor->lte($end)) {
            $day = $cursor->toDateString();
            $voucherRow = $vouchers->get($day);
            $orderRow = $orders->get($day);
            $days->push([
                'day' => $day,
                'orders' => (int) ($voucherRow?->sales ?? 0) + (int) ($orderRow?->sales ?? 0),
                'total' => (int) ($voucherRow?->total ?? 0) + (int) ($orderRow?->total ?? 0),
            ]);
            $cursor->addDay();
        }

        return $days;
    }

    private function voucherSales(DateTimeInterface $from, DateTimeInterface $to): int
    {
        return (int) Voucher::query()
            ->whereNotNull('first_used_at')
            ->whereBetween('first_used_at', [$from, $to])
            ->whereNotIn('id', $this->orderedVoucherIds())
            ->sum('price_minor');
    }

    private function orderSales(DateTimeInterface $from, DateTimeInterface $to): int
    {
        return (int) Order::query()
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Fulfilled])
            ->whereBetween('paid_at', [$from, $to])
            ->sum(DB::raw('COALESCE(net_minor, amount_minor)'));
    }

    /**
     * @return Builder<Order>
     */
    private function orderedVoucherIds(): Builder
    {
        return Order::query()->whereNotNull('voucher_id')->select('voucher_id');
    }
}
