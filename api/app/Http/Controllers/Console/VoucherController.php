<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Radius\RadiusProvisioner;
use App\Domain\Tenancy\AuditLogger;
use App\Domain\Voucher\VoucherCard;
use App\Domain\Voucher\VoucherCode;
use App\Domain\Voucher\VoucherCodeHasher;
use App\Domain\Voucher\VoucherStatus;
use App\Http\Resources\VoucherResource;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VoucherController
{
    use AuthorizesRequests;

    /**
     * Voucher search across the operator's whole estate.
     */
    public function index(Request $request, VoucherCodeHasher $hasher): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Voucher::class);

        $vouchers = Voucher::query()
            ->with(['plan', 'usage'])
            ->when(
                $request->filled('status'),
                fn (Builder $query) => $query->where('status', $request->string('status')),
            )
            ->when(
                $request->filled('plan_id'),
                fn (Builder $query) => $query->where('plan_id', $request->integer('plan_id')),
            )
            ->when(
                $request->filled('batch_id'),
                fn (Builder $query) => $query->where('batch_id', $request->integer('batch_id')),
            )
            ->when(
                $request->filled('mac'),
                fn (Builder $query) => $query->where('bound_mac', $request->string('mac')),
            )
            ->when(
                $request->filled('code'),
                /*
                 * Codes are encrypted with a non-deterministic cipher, so the
                 * column cannot be searched. A full code is found by its keyed
                 * hash; a partial one falls back to the stored suffix, which is
                 * what a customer reads out over the phone.
                 */
                function (Builder $query) use ($request, $hasher): void {
                    $code = VoucherCode::normalise($request->string('code')->value());

                    $query->where(function (Builder $inner) use ($code, $hasher): void {
                        $inner->where('code_hash', $hasher->hash($code))
                            ->orWhere('code_suffix', substr($code, -4));
                    });
                },
            )
            ->latest('id')
            ->paginate($request->integer('per_page', 50));

        return VoucherResource::collection($vouchers);
    }

    public function show(Voucher $voucher): VoucherResource
    {
        $this->authorize('view', $voucher);

        return new VoucherResource($voucher->load(['plan', 'batch', 'devices', 'usage']));
    }

    /**
     * Returns a single voucher's cleartext code.
     *
     * For reprinting a card a customer has damaged or lost. Audited on every call,
     * because this is how a code that was already sold would be read a second
     * time, and the audit trail is the only thing that makes that visible.
     */
    public function reveal(Voucher $voucher, AuditLogger $audit): JsonResponse
    {
        $this->authorize('reveal', $voucher);

        $audit->record('voucher.revealed', $voucher, ['status' => $voucher->status->value]);

        return response()->json([
            'code' => $voucher->displayCode(),
            'redemption_url' => VoucherCard::redemptionUrl($voucher->code),
        ]);
    }

    /**
     * Withdraws a single code.
     *
     * Its RADIUS authorisation is removed, so the next authentication fails. A
     * session already running is unaffected -- ending that needs a
     * Disconnect-Request, which the enforcement layer issues.
     */
    public function disable(
        Request $request,
        Voucher $voucher,
        RadiusProvisioner $provisioner,
        AuditLogger $audit,
    ): VoucherResource {
        $this->authorize('disable', $voucher);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $provisioner->revoke($voucher);

        $voucher->update([
            'status' => VoucherStatus::Disabled,
            'disabled_at' => now(),
            'disabled_by_user_id' => $request->user()->id,
            'disable_reason' => $validated['reason'] ?? null,
        ]);

        $audit->record('voucher.disabled', $voucher, ['reason' => $validated['reason'] ?? null]);

        return new VoucherResource($voucher->load('plan'));
    }
}
