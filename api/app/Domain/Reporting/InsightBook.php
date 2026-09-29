<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Billing\CollectionBook;
use App\Domain\Billing\OrderStatus;
use App\Domain\Customers\CustomerDirectory;
use App\Domain\Tenancy\UserRole;
use App\Domain\Voucher\VoucherStatus;
use App\Models\Customer;
use App\Models\NasDevice;
use App\Models\Order;
use App\Models\Plan;
use App\Models\RadAcct;
use App\Models\Site;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Models\VoucherUsage;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Everything the operator Analytics screen reads, in one pass.
 *
 * Money follows the same rule as the rest of the console: the operator books a
 * printed card when it is first used on the Wi‑Fi, while the agent counter books
 * it when it is printed and handed over. Both figures appear here, side by side,
 * because the gap between them is stock sitting in someone's pocket.
 */
final readonly class InsightBook
{
    /** Cards whose shelf life ends inside this window are worth selling first. */
    private const SOON_DAYS = 7;

    /** A phone that has not appeared for this long has drifted away. */
    private const IDLE_DAYS = 7;

    public function __construct(
        private CollectionBook $collections,
        private CustomerDirectory $directory,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forPeriod(string $period): array
    {
        $summary = $this->collections->summary($period);
        $from = Carbon::parse($summary['from']);
        $to = Carbon::parse($summary['to']);

        return [
            'period' => $period,
            'from' => $summary['from'],
            'to' => $summary['to'],
            'income' => $this->income($summary),
            'customers' => $this->customers($from),
            'stock' => $this->stock(),
            'agents' => $this->agents($from, $to),
            'sites' => $this->sites($from, $to),
            'plans' => $this->plans($from, $to),
            'series' => $summary['series'],
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function income(array $summary): array
    {
        $total = (int) $summary['total_minor'];
        $previous = (int) $summary['previous_total_minor'];

        return [
            'total_minor' => $total,
            'lipia_minor' => (int) $summary['lipia_minor'],
            'kadi_minor' => (int) $summary['kadi_minor'],
            'lipia_count' => (int) $summary['lipia_count'],
            'kadi_count' => (int) $summary['kadi_count'],
            'previous_total_minor' => $previous,
            'change_pct' => $this->changePct($total, $previous),
            'today_minor' => $this->takeBetween(now()->startOfDay(), now()),
            'month_minor' => $this->takeBetween(now()->startOfMonth(), now()),
            'lifetime_minor' => $this->takeBetween(Carbon::create(2000, 1, 1), now()),
        ];
    }

    /**
     * Who is on the radio right now, who merely has credit, and who has drifted.
     *
     * @return array<string, mixed>
     */
    private function customers(DateTimeInterface $from): array
    {
        $total = (int) Customer::query()->count();
        $hai = $this->directory->haiIds()->count();
        $onlineIds = $this->directory->onlineIds();

        $repeat = (int) Voucher::query()
            ->whereNotNull('customer_id')
            ->select('customer_id')
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        return [
            'total' => $total,
            'online_now' => $onlineIds->count(),
            'sessions_open' => $this->openSessionCount(),
            'hai' => $hai,
            'kimya' => max(0, $total - $hai),
            'new_in_period' => (int) Customer::query()->where('first_seen_at', '>=', $from)->count(),
            'new_today' => (int) Customer::query()->where('first_seen_at', '>=', now()->startOfDay())->count(),
            'repeat_buyers' => $repeat,
            'idle' => (int) Customer::query()
                ->where('last_seen_at', '<', now()->subDays(self::IDLE_DAYS))
                ->count(),
            'idle_days' => self::IDLE_DAYS,
        ];
    }

    /**
     * Card stock by state, including the two that cost money: cards whose shelf
     * life ran out unsold, and sold cards whose validity has elapsed.
     *
     * @return array<string, mixed>
     */
    private function stock(): array
    {
        $rows = Voucher::query()
            ->selectRaw('status, COUNT(*) as cards, SUM(price_minor) as value')
            ->groupBy('status')
            ->get();

        $count = function (VoucherStatus $status) use ($rows): int {
            $row = $rows->first(fn ($item) => $this->statusValue($item) === $status->value);

            return (int) ($row->cards ?? 0);
        };

        $value = function (VoucherStatus $status) use ($rows): int {
            $row = $rows->first(fn ($item) => $this->statusValue($item) === $status->value);

            return (int) ($row->value ?? 0);
        };

        /*
         * Expiry is stamped lazily, when RADIUS next looks at the voucher. A card
         * whose window has already closed is counted here even if nobody has
         * touched it since, otherwise the figure reads better than reality.
         */
        $timeUp = (int) Voucher::query()
            ->where('status', VoucherStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->count();

        $deadStock = Voucher::query()
            ->where('status', VoucherStatus::Unused)
            ->whereNotNull('shelf_expires_at')
            ->where('shelf_expires_at', '<', now())
            ->selectRaw('COUNT(*) as cards, SUM(price_minor) as value')
            ->first();

        $expiringSoon = Voucher::query()
            ->where('status', VoucherStatus::Unused)
            ->whereNotNull('shelf_expires_at')
            ->whereBetween('shelf_expires_at', [now(), now()->addDays(self::SOON_DAYS)])
            ->selectRaw('COUNT(*) as cards, SUM(price_minor) as value')
            ->first();

        $atCounter = Voucher::query()
            ->where('status', VoucherStatus::Unused)
            ->whereNotNull('printed_at')
            ->selectRaw('COUNT(*) as cards, SUM(price_minor) as value')
            ->first();

        $inOffice = (int) Voucher::query()
            ->where('status', VoucherStatus::Unused)
            ->whereNull('printed_at')
            ->count();

        return [
            'unused' => $count(VoucherStatus::Unused),
            'active' => $count(VoucherStatus::Active),
            'exhausted' => $count(VoucherStatus::Exhausted),
            'disabled' => $count(VoucherStatus::Disabled),
            'expired' => $count(VoucherStatus::Expired) + $timeUp,
            'expired_stored' => $count(VoucherStatus::Expired),
            'time_up' => $timeUp,
            'expired_value_minor' => $value(VoucherStatus::Expired),
            'at_counter' => (int) ($atCounter->cards ?? 0),
            'at_counter_value_minor' => (int) ($atCounter->value ?? 0),
            'in_office' => $inOffice,
            'expiring_soon' => (int) ($expiringSoon->cards ?? 0),
            'expiring_soon_value_minor' => (int) ($expiringSoon->value ?? 0),
            'dead_stock' => (int) ($deadStock->cards ?? 0),
            'dead_stock_value_minor' => (int) ($deadStock->value ?? 0),
            'soon_days' => self::SOON_DAYS,
        ];
    }

    /**
     * Per-agent takings: cash booked at the counter on print, internet actually
     * delivered, and how much stock is left to sell.
     *
     * @return list<array<string, mixed>>
     */
    private function agents(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $agents = User::query()
            ->where('role', UserRole::Agent)
            ->with('sites')
            ->orderBy('name')
            ->get();

        if ($agents->isEmpty()) {
            return [];
        }

        $batches = VoucherBatch::query()
            ->whereNotNull('assigned_agent_id')
            ->get(['id', 'assigned_agent_id']);

        $batchIds = $batches->pluck('id')->all();
        $sold = $this->batchTotals('printed_at', $from, $to, $batchIds);
        $delivered = $this->batchTotals('first_used_at', $from, $to, $batchIds);
        $stock = $this->batchStock($batchIds);

        $rows = $agents->map(function (User $agent) use ($batches, $sold, $delivered, $stock): array {
            $owned = $batches->where('assigned_agent_id', $agent->id)->pluck('id');

            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'sites' => $agent->sites->pluck('name')->values()->all(),
                'sold_count' => $this->sumOver($owned, $sold, 'cards'),
                'sold_minor' => $this->sumOver($owned, $sold, 'value'),
                'delivered_count' => $this->sumOver($owned, $delivered, 'cards'),
                'delivered_minor' => $this->sumOver($owned, $delivered, 'value'),
                'stock' => $this->sumOver($owned, $stock, 'cards'),
            ];
        });

        return $rows
            ->sortByDesc('sold_minor')
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sites(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $sites = Site::query()->with('nasDevices')->orderBy('name')->get();

        if ($sites->isEmpty()) {
            return [];
        }

        $online = $this->onlineCountsBySite($sites);
        $batches = VoucherBatch::query()->whereNotNull('site_id')->get(['id', 'site_id']);
        $stock = $this->batchStock($batches->pluck('id')->all());

        return $sites->map(function (Site $site) use ($from, $to, $online, $batches, $stock): array {
            $totals = $this->collections->totals($from, $to, $site->id);
            $owned = $batches->where('site_id', $site->id)->pluck('id');

            return [
                'id' => $site->id,
                'name' => $site->name,
                'total_minor' => $totals['lipia'] + $totals['kadi'],
                'lipia_minor' => $totals['lipia'],
                'kadi_minor' => $totals['kadi'],
                'online' => (int) $online->get($site->id, 0),
                'cards_left' => $this->sumOver($owned, $stock, 'cards'),
                'router_quiet' => $site->nasDevices->isEmpty()
                    || $site->nasDevices->contains(fn (NasDevice $device) => $device->isQuiet()),
                'routers' => $site->nasDevices->count(),
            ];
        })
            ->sortByDesc('total_minor')
            ->values()
            ->all();
    }

    /**
     * Which bundles actually sell, counting portal orders and used cards alike.
     *
     * @return list<array<string, mixed>>
     */
    private function plans(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $kadi = Voucher::query()
            ->whereNotNull('first_used_at')
            ->whereBetween('first_used_at', [$from, $to])
            ->whereNotIn('id', $this->orderedVoucherIds())
            ->selectRaw('plan_id, COUNT(*) as cards, SUM(price_minor) as value')
            ->groupBy('plan_id')
            ->get()
            ->keyBy('plan_id');

        $lipia = Order::query()
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Fulfilled])
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('plan_id, COUNT(*) as orders, SUM(COALESCE(net_minor, amount_minor)) as value')
            ->groupBy('plan_id')
            ->get()
            ->keyBy('plan_id');

        $planIds = $kadi->keys()->merge($lipia->keys())->unique();

        if ($planIds->isEmpty()) {
            return [];
        }

        return Plan::withTrashed()
            ->whereIn('id', $planIds)
            ->get(['id', 'name', 'price_minor'])
            ->map(function (Plan $plan) use ($kadi, $lipia): array {
                $kadiRow = $kadi->get($plan->id);
                $lipiaRow = $lipia->get($plan->id);
                $kadiValue = (int) ($kadiRow->value ?? 0);
                $lipiaValue = (int) ($lipiaRow->value ?? 0);

                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price_minor' => $plan->price_minor,
                    'kadi_count' => (int) ($kadiRow->cards ?? 0),
                    'lipia_count' => (int) ($lipiaRow->orders ?? 0),
                    'total_minor' => $kadiValue + $lipiaValue,
                ];
            })
            ->sortByDesc('total_minor')
            ->values()
            ->all();
    }

    /**
     * Grouped card takings for one moment column, keyed by batch.
     *
     * Vouchers sold through the portal are excluded: that money is already Lipia,
     * and counting the redemption again would pay the agent twice.
     *
     * @param  list<int>  $batchIds
     * @return Collection<int, object>
     */
    private function batchTotals(
        string $column,
        DateTimeInterface $from,
        DateTimeInterface $to,
        array $batchIds,
    ): Collection {
        if ($batchIds === []) {
            return collect();
        }

        return Voucher::query()
            ->whereIn('batch_id', $batchIds)
            ->whereNotNull($column)
            ->whereBetween($column, [$from, $to])
            ->whereNotIn('id', $this->orderedVoucherIds())
            ->selectRaw('batch_id, COUNT(*) as cards, SUM(price_minor) as value')
            ->groupBy('batch_id')
            ->get()
            ->keyBy('batch_id');
    }

    /**
     * @param  list<int>  $batchIds
     * @return Collection<int, object>
     */
    private function batchStock(array $batchIds): Collection
    {
        if ($batchIds === []) {
            return collect();
        }

        return Voucher::query()
            ->whereIn('batch_id', $batchIds)
            ->where('status', VoucherStatus::Unused)
            ->selectRaw('batch_id, COUNT(*) as cards')
            ->groupBy('batch_id')
            ->get()
            ->keyBy('batch_id');
    }

    /**
     * @param  Collection<int, int>  $batchIds
     * @param  Collection<int, object>  $rows
     */
    private function sumOver(Collection $batchIds, Collection $rows, string $field): int
    {
        return (int) $batchIds->sum(fn (int $id) => (int) ($rows->get($id)->{$field} ?? 0));
    }

    private function openSessionCount(): int
    {
        $usernames = VoucherUsage::query()->pluck('username');

        if ($usernames->isEmpty()) {
            return 0;
        }

        return (int) RadAcct::query()->open()->whereIn('username', $usernames)->count();
    }

    /**
     * Open sessions per site, matched on both the public RADIUS address and the
     * hotspot LAN address, since accounting can arrive under either.
     *
     * @param  Collection<int, Site>  $sites
     * @return Collection<int, int>
     */
    private function onlineCountsBySite(Collection $sites): Collection
    {
        $usernames = VoucherUsage::query()->pluck('username');

        if ($usernames->isEmpty()) {
            return collect();
        }

        $siteByIp = [];

        foreach ($sites as $site) {
            foreach ($site->nasDevices as $device) {
                foreach (array_filter([$device->nasname, $device->api_host]) as $ip) {
                    $siteByIp[$ip] = $site->id;
                }
            }
        }

        if ($siteByIp === []) {
            return collect();
        }

        return RadAcct::query()
            ->open()
            ->whereIn('username', $usernames)
            ->whereIn('nasipaddress', array_keys($siteByIp))
            ->selectRaw('nasipaddress, COUNT(*) as sessions')
            ->groupBy('nasipaddress')
            ->get()
            ->reduce(function (Collection $carry, $row) use ($siteByIp): Collection {
                $siteId = $siteByIp[$row->nasipaddress] ?? null;

                if ($siteId === null) {
                    return $carry;
                }

                return $carry->put($siteId, (int) $carry->get($siteId, 0) + (int) $row->sessions);
            }, collect());
    }

    private function takeBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        $totals = $this->collections->totals($from, $to);

        return $totals['lipia'] + $totals['kadi'];
    }

    private function changePct(int $current, int $previous): ?int
    {
        if ($previous === 0) {
            return null;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }

    private function statusValue(object $row): string
    {
        $status = $row->status ?? null;

        return $status instanceof VoucherStatus ? $status->value : (string) $status;
    }

    /**
     * @return Builder<Order>
     */
    private function orderedVoucherIds(): Builder
    {
        return Order::query()->whereNotNull('voucher_id')->select('voucher_id');
    }
}
