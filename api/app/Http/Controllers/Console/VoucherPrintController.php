<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Voucher\VoucherCard;
use App\Domain\Voucher\VoucherStatus;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The printable card layout for a batch.
 *
 * Returns HTML rather than JSON, and a PDF is deliberately not generated: the
 * browser's own print dialog already produces one, it lets the operator pick a
 * paper size and check the preview before committing a sheet, and it avoids
 * shipping a headless browser to render something the client can render itself.
 *
 * Each voucher is printed at most once. Re-running a batch only emits codes that
 * have never been on paper; already-printed cleartext must not reappear as a
 * second physical card.
 */
class VoucherPrintController
{
    use AuthorizesRequests;

    public function __invoke(Request $request, VoucherBatch $batch, AuditLogger $audit): View
    {
        $this->authorize('print', $batch);

        $request->validate([
            'from' => ['nullable', 'integer', 'min:1'],
            'to' => ['nullable', 'integer', 'min:1'],
            'include_used' => ['boolean'],
        ]);

        $columns = (int) config('kasi.voucher.print_columns');
        $rows = (int) config('kasi.voucher.print_rows');

        $vouchers = $batch->vouchers()
            ->with('plan')
            // Never reprint a code that has already been on a sheet.
            ->whereNull('printed_at')
            ->unless(
                $request->boolean('include_used'),
                fn ($query) => $query->where('status', VoucherStatus::Unused),
            )
            ->orderBy('id')
            ->when($request->filled('from'), fn ($query) => $query->skip($request->integer('from') - 1))
            ->limit($this->limitFor($request, $columns * $rows))
            ->get();

        if ($vouchers->isEmpty()) {
            throw ValidationException::withMessages([
                'batch' => 'There are no unprinted vouchers left in this batch. Each code can only be printed once.',
            ]);
        }

        $batch->loadMissing(['plan', 'site', 'assignedAgent', 'tenant']);

        $cards = $vouchers->map(
            fn ($voucher) => VoucherCard::for($voucher, $batch->tenant, $batch->site),
        );

        DB::transaction(function () use ($batch, $vouchers, $audit, $request, $cards): void {
            $now = now();
            Voucher::withoutTenantScope()
                ->whereIn('id', $vouchers->pluck('id'))
                ->whereNull('printed_at')
                ->update(['printed_at' => $now, 'updated_at' => $now]);

            $batch->increment('print_count', 1, ['printed_at' => $now]);

            $audit->record('voucher_batch.printed', $batch, [
                'cards' => $cards->count(),
                'from' => $request->integer('from') ?: 1,
            ]);
        });

        return view('vouchers.print', [
            'batch' => $batch,
            'cards' => $cards,
            'columns' => $columns,
            'rows' => $rows,
            'cardHeightMm' => $this->cardHeightMm($rows),
        ]);
    }

    private function limitFor(Request $request, int $perSheet): int
    {
        if (! $request->filled('to')) {
            return $perSheet;
        }

        $from = max(1, $request->integer('from') ?: 1);
        $requested = $request->integer('to') - $from + 1;

        return max(1, min($requested, $perSheet * 20));
    }

    private function cardHeightMm(int $rows): float
    {
        return round((297 - 16) / max(1, $rows), 2);
    }
}
