<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Tenancy\PortalContext;
use App\Domain\Voucher\VoucherRedeemer;
use App\Http\Resources\VoucherResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RedeemController
{
    public function __invoke(Request $request, VoucherRedeemer $redeemer, PortalContext $context): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $voucher = $redeemer->redeem($validated['code'], $context)->loadMissing('plan');

        return response()->json([
            'code' => $voucher->code,
            'display_code' => $voucher->displayCode(),
            'session' => [
                'plan' => $voucher->plan?->name,
                'validity_seconds' => $voucher->validity_seconds,
                'duration_seconds' => $voucher->duration_seconds,
                'data_cap_bytes' => $voucher->data_cap_bytes,
                'device_limit' => $voucher->device_limit,
                'expires_at' => $voucher->expires_at?->toIso8601String(),
            ],
            'voucher' => new VoucherResource($voucher),
        ]);
    }
}
