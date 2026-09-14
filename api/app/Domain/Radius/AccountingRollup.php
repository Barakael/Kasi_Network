<?php

declare(strict_types=1);

namespace App\Domain\Radius;

use App\Models\RadAcct;
use App\Models\VoucherUsage;
use Illuminate\Support\Carbon;

/**
 * Folds closed and recently-updated accounting rows into voucher_usage.
 *
 * sqlcounter reads this table on every Access-Request. Summing radacct there
 * instead would scan a growing history on the authentication path. Open
 * sessions are left as a small delta that the counter query adds at read time.
 */
final readonly class AccountingRollup
{
    public function rollup(?Carbon $lookback = null): int
    {
        $lookback ??= now()->subMinutes((int) config('kasi.radius.rollup_lookback_minutes'));
        $updated = 0;

        VoucherUsage::withoutTenantScope()
            ->orderBy('id')
            ->chunkById(200, function ($usages) use ($lookback, &$updated): void {
                foreach ($usages as $usage) {
                    $updated += $this->rollupOne($usage, $lookback) ? 1 : 0;
                }
            });

        return $updated;
    }

    public function rollupOne(VoucherUsage $usage, Carbon $lookback): bool
    {
        $since = $usage->rolled_up_through?->copy()->subMinutes(1) ?? $lookback->copy()->subYear();

        $closed = RadAcct::query()
            ->where('username', $usage->username)
            ->whereNotNull('acctstoptime')
            ->where('acctstoptime', '>=', $since)
            ->get();

        if ($closed->isEmpty()) {
            $usage->forceFill(['rolled_up_through' => now()])->save();

            return false;
        }

        $seconds = (int) $closed->sum(fn (RadAcct $row): int => $row->elapsedSeconds());
        $bytesIn = (int) $closed->sum('acctinputoctets');
        $bytesOut = (int) $closed->sum('acctoutputoctets');

        /*
         * Totals are recomputed from all closed sessions, not incremented by
         * the chunk. Interim updates that land while a sweep is running would
         * otherwise be counted twice against the lookback overlap.
         */
        $totals = RadAcct::query()
            ->where('username', $usage->username)
            ->whereNotNull('acctstoptime')
            ->selectRaw('COALESCE(SUM(acctsessiontime), 0) as seconds')
            ->selectRaw('COALESCE(SUM(acctinputoctets), 0) as bytes_in')
            ->selectRaw('COALESCE(SUM(acctoutputoctets), 0) as bytes_out')
            ->selectRaw('COUNT(*) as sessions')
            ->selectRaw('MAX(acctstoptime) as last_at')
            ->first();

        $usage->forceFill([
            'seconds_used' => (int) ($totals->seconds ?? $seconds),
            'bytes_in' => (int) ($totals->bytes_in ?? $bytesIn),
            'bytes_out' => (int) ($totals->bytes_out ?? $bytesOut),
            'session_count' => (int) ($totals->sessions ?? $closed->count()),
            'last_session_at' => $totals->last_at ?? $closed->max('acctstoptime'),
            'rolled_up_through' => now(),
        ])->save();

        return true;
    }
}
