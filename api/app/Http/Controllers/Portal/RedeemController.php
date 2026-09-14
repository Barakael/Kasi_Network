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

        $voucher = $redeemer->redeem($validated['code'], $context);

        return response()->json([
            'code' => $voucher->code,
            'display_code' => $voucher->displayCode(),
            'voucher' => new VoucherResource($voucher),
        ]);
    }
}
