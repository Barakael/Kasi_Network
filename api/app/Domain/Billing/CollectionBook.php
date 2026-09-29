<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Models\Order;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Splits the till into Lipia (paid orders) and kadi.
 *
 * Operator Collections count a printed card when it is first used. The agent
 * desk counts it when it is printed — cash in hand at the counter.
 */
final readonly class CollectionBook
{
    /**
     * @return array<string, mixed>
     */
    public function summary(
        string $period = 'day',
        ?int $siteId = null,
        ?int $agentId = null,
    ): array {
        [$from, $to] = $this->window($period);
        [$lastFrom, $lastTo] = $this->previousWindow($period, $from);

        $current = $this->totals($from, $to, $siteId, $agentId);
        $previous = $this->totals($lastFrom, $lastTo, $siteId, $agentId);

        return [
            'period' => $period,
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'lipia_minor' => $current['lipia'],
            'kadi_minor' => $current['kadi'],
            'total_minor' => $current['lipia'] + $current['kadi'],
            'lipia_count' => $current['lipia_count'],
            'kadi_count' => $current['kadi_count'],
            'previous_total_minor' => $previous['lipia'] + $previous['kadi'],
            'previous_lipia_minor' => $previous['lipia'],
            'previous_kadi_minor' => $previous['kadi'],
            'series' => $this->tillSeries($from, $to, $siteId, $agentId),
        ];
    }

    /**
     * @return array{lipia: int, kadi: int, lipia_count: int, kadi_count: int}
     */
    public function totals(
        DateTimeInterface $from,
        DateTimeInterface $to,
        ?int $siteId = null,
        ?int $agentId = null,
    ): array {
        $lipiaQuery = Order::query()
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Fulfilled])
            ->whereBetween('paid_at', [$from, $to])
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId));

        // Lipia is the portal till. An agent filter does not claim it.
        $lipia = $agentId === null
            ? (int) $lipiaQuery->sum(DB::raw('COALESCE(net_minor, amount_minor)'))
            : 0;
        $lipiaCount = $agentId === null ? (int) (clone $lipiaQuery)->count() : 0;

        $kadiQuery = $this->kadiMomentQuery('first_used_at', $from, $to, $siteId, $agentId);

        return [
            'lipia' => $lipia,
            'kadi' => (int) (clone $kadiQuery)->sum('price_minor'),
            'lipia_count' => $lipiaCount,
            'kadi_count' => (int) (clone $kadiQuery)->count(),
        ];
    }

    /**
     * Agent counter till: cash when the card is printed and handed over.
     *
     * @return array{kadi: int, kadi_count: int}
     */
    public function printedTotals(
        DateTimeInterface $from,
        DateTimeInterface $to,
        ?int $siteId = null,
        ?int $agentId = null,
    ): array {
        $query = $this->kadiMomentQuery('printed_at', $from, $to, $siteId, $agentId);

        return [
            'kadi' => (int) (clone $query)->sum('price_minor'),
            'kadi_count' => (int) (clone $query)->count(),
        ];
    }

    /**
     * Daily kadi take for a site/agent window. Empty days are filled so a chart
     * still draws when the till is quiet.
     *
     * @return list<array{day: string, orders: int, total: int}>
     */
    public function kadiSeries(
        DateTimeInterface $from,
        DateTimeInterface $to,
        ?int $siteId = null,
        ?int $agentId = null,
        string $moment = 'first_used_at',
    ): array {
        $column = $moment === 'printed_at' ? 'printed_at' : 'first_used_at';
        $rows = $this->kadiMomentQuery($column, $from, $to, $siteId, $agentId)
            ->selectRaw("DATE({$column}) as day, COUNT(*) as sales, SUM(price_minor) as total")
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $series = [];
        $cursor = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $row = $rows->get($key);
            $series[] = [
                'day' => $key,
                'orders' => (int) ($row?->sales ?? 0),
                'total' => (int) ($row?->total ?? 0),
            ];
            $cursor->addDay();
        }

        return $series;
    }

    /**
     * Daily Lipia + kadi take. Agent windows stay kadi-only so portal money
     * is never attributed to a reseller.
     *
     * @return list<array{day: string, orders: int, total: int}>
     */
    public function tillSeries(
        DateTimeInterface $from,
        DateTimeInterface $to,
        ?int $siteId = null,
        ?int $agentId = null,
    ): array {
        $kadi = collect($this->kadiSeries($from, $to, $siteId, $agentId))->keyBy('day');

        $lipiaRows = $agentId !== null
            ? collect()
            : Order::query()
                ->whereIn('status', [OrderStatus::Paid, OrderStatus::Fulfilled])
                ->whereBetween('paid_at', [$from, $to])
                ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
                ->selectRaw('DATE(paid_at) as day, COUNT(*) as sales, SUM(COALESCE(net_minor, amount_minor)) as total')
                ->groupBy('day')
                ->get()
                ->keyBy('day');

        return $kadi->values()->map(function (array $row) use ($lipiaRows): array {
            $lipia = $lipiaRows->get($row['day']);

            return [
                'day' => $row['day'],
                'orders' => $row['orders'] + (int) data_get($lipia, 'sales', 0),
                'total' => $row['total'] + (int) data_get($lipia, 'total', 0),
            ];
        })->all();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(string $period): array
    {
        return match ($period) {
            'week' => [now()->startOfWeek(), now()],
            'month' => [now()->startOfMonth(), now()],
            default => [now()->startOfDay(), now()],
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function previousWindow(string $period, DateTimeInterface $from): array
    {
        $start = Carbon::parse($from);

        return match ($period) {
            'week' => [$start->copy()->subWeek()->startOfWeek(), $start->copy()->subWeek()->endOfWeek()],
            'month' => [$start->copy()->subMonth()->startOfMonth(), $start->copy()->subMonth()->endOfMonth()],
            default => [$start->copy()->subDay()->startOfDay(), $start->copy()->subDay()->endOfDay()],
        };
    }

    /**
     * @return Builder<Voucher>
     */
    private function kadiMomentQuery(
        string $column,
        DateTimeInterface $from,
        DateTimeInterface $to,
        ?int $siteId,
        ?int $agentId,
    ): Builder {
        return Voucher::query()
            ->whereNotNull($column)
            ->whereBetween($column, [$from, $to])
            ->whereNotIn('id', Order::query()->whereNotNull('voucher_id')->select('voucher_id'))
            ->when(
                $siteId || $agentId,
                fn ($q) => $q->whereIn('batch_id', $this->batchIds($siteId, $agentId)),
            );
    }

    /**
     * @return Builder<VoucherBatch>
     */
    private function batchIds(?int $siteId, ?int $agentId): Builder
    {
        return VoucherBatch::query()
            ->select('id')
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->when($agentId, fn ($q) => $q->where('assigned_agent_id', $agentId));
    }
}
