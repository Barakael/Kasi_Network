<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Voucher\VoucherCard;
use App\Domain\Voucher\VoucherStatus;
use App\Models\VoucherBatch;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The printable card layout for a batch.
 *
 * Returns HTML rather than JSON, and a PDF is deliberately not generated: the
 * browser's own print dialog already produces one, it lets the operator pick a
 * paper size and check the preview before committing a sheet, and it avoids
 * shipping a headless browser to render something the client can render itself.
 *
 * The console cannot simply open this in a tab, because a Sanctum token travels
 * in a header and a plain navigation carries none. It fetches the document and
 * opens it from a blob instead, which the self-contained markup below allows.
 *
 * This is the one console surface an agent can reach, which is what the reseller
 * tier amounts to in practice. It is also the only place other than the reveal
 * endpoint where cleartext codes leave the API, so every render is audited.
 */
class VoucherPrintController
{
    use AuthorizesRequests;

    public function __invoke(Request $request, VoucherBatch $batch, AuditLogger $audit): View
    {
        $this->authorize('print', $batch);

        $request->validate([
            // Reprinting a subset, for the common case of a jammed printer
            // ruining the last sheet of a long run.
            'from' => ['nullable', 'integer', 'min:1'],
            'to' => ['nullable', 'integer', 'min:1'],
            'include_used' => ['boolean'],
        ]);

        $columns = (int) config('kasi.voucher.print_columns');
        $rows = (int) config('kasi.voucher.print_rows');

        $vouchers = $batch->vouchers()
            ->with('plan')
            ->unless(
                $request->boolean('include_used'),
                /*
                 * Redeemed and withdrawn codes are left off by default. A card
                 * printed for a code that no longer works is worse than a missing
                 * card: it gets sold, and the customer comes back.
                 */
                fn ($query) => $query->where('status', VoucherStatus::Unused),
            )
            ->orderBy('id')
            ->when($request->filled('from'), fn ($query) => $query->skip($request->integer('from') - 1))
            ->limit($this->limitFor($request, $columns * $rows))
            ->get();

        if ($vouchers->isEmpty()) {
            throw ValidationException::withMessages([
                'batch' => 'There are no unused vouchers left in this batch to print.',
            ]);
        }

        $batch->loadMissing(['plan', 'site', 'assignedAgent', 'tenant']);

        $cards = $vouchers->map(
            fn ($voucher) => VoucherCard::for($voucher, $batch->tenant, $batch->site),
        );

        /*
         * Counted rather than flagged, so an operator can see that a batch has
         * been run off four times -- which is how a duplicated set of cards in
         * circulation gets noticed. Neither column is fillable, since a request
         * has no business setting either.
         */
        $batch->increment('print_count', 1, ['printed_at' => now()]);

        $audit->record('voucher_batch.printed', $batch, [
            'cards' => $cards->count(),
            'from' => $request->integer('from') ?: 1,
        ]);

        return view('vouchers.print', [
            'batch' => $batch,
            'cards' => $cards,
            'columns' => $columns,
            'rows' => $rows,
            'cardHeightMm' => $this->cardHeightMm($rows),
        ]);
    }

    /**
     * How many cards to render.
     *
     * Capped at a sheet's worth by default. A ten-thousand-voucher batch rendered
     * in one document is hundreds of megabytes of inline SVG and will hang the
     * print dialog, so pagination is the default rather than an option.
     */
    private function limitFor(Request $request, int $perSheet): int
    {
        if (! $request->filled('to')) {
            return $perSheet;
        }

        $from = max(1, $request->integer('from') ?: 1);
        $requested = $request->integer('to') - $from + 1;

        // Twenty sheets at a time: enough for a real print run, short of the
        // point where the browser struggles.
        return max(1, min($requested, $perSheet * 20));
    }

    /**
     * Card height in millimetres, derived so the grid fills exactly one sheet.
     *
     * A4 is 297mm tall, less the 8mm print margin at top and bottom.
     */
    private function cardHeightMm(int $rows): float
    {
        return round((297 - 16) / max(1, $rows), 2);
    }
}
