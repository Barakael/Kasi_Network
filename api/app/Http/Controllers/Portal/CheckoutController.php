<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Tenancy\PortalContext;
use App\Domain\Voucher\VoucherIssuer;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issues a voucher for a chosen package when live mobile money is not on.
 *
 * Used on a local bench so the customer flow is still: pick a bundle, then
 * receive a code. Production operators should set KASI_PORTAL_DEMO_CHECKOUT=false
 * and take payment through Snippe or a printed card.
 */
class CheckoutController
{
    public function __invoke(Request $request, PortalContext $context, VoucherIssuer $issuer): JsonResponse
    {
        if (! config('kasi.demo_checkout')) {
            return response()->json([
                'message' => 'Online checkout is not available on this hotspot.',
                'code' => 'checkout_unavailable',
            ], 422);
        }

        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        $plan = Plan::query()
            ->where('is_active', true)
            ->where('is_sold_online', true)
            ->findOrFail($validated['plan_id']);

        $voucher = $issuer->issueOne($plan);

        return response()->json([
            'code' => $voucher->code,
            'display_code' => $voucher->displayCode(),
            'plan' => $plan->name,
        ], 201);
    }
}
