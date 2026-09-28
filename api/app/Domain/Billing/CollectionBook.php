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
 * Splits the till into Lipia (paid orders) and kadi (first-used printed cards).
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

        $kadiQuery = Voucher::query()
            ->whereNotNull('first_used_at')
            ->whereBetween('first_used_at', [$from, $to])
            ->whereNotIn('id', Order::query()->whereNotNull('voucher_id')->select('voucher_id'))
            ->when(
                $siteId || $agentId,
                fn ($q) => $q->whereIn('batch_id', $this->batchIds($siteId, $agentId)),
            );

        return [
            'lipia' => $lipia,
            'kadi' => (int) $kadiQuery->sum('price_minor'),
            'lipia_count' => $lipiaCount,
            'kadi_count' => (int) (clone $kadiQuery)->count(),
        ];
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
