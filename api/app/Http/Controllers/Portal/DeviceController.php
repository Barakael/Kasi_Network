<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Router\HotspotHostReader;
use App\Domain\Tenancy\PortalContext;
use App\Domain\Voucher\MacBinder;
use App\Domain\Voucher\VoucherCode;
use App\Domain\Voucher\VoucherCodeHasher;
use App\Http\Resources\VoucherDeviceResource;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController
{
    public function nearby(PortalContext $context, HotspotHostReader $reader): JsonResponse
    {
        if (! $context->nasDevice?->hasApiCredentials()) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $reader->unauthenticated($context->nasDevice),
        ]);
    }

    public function store(
        Request $request,
        PortalContext $context,
        MacBinder $binder,
        VoucherCodeHasher $hasher,
    ): JsonResponse {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'mac' => ['required', 'string', 'max:32'],
            'label' => ['nullable', 'string', 'max:80'],
        ]);

        $voucher = Voucher::query()
            ->where('code_hash', $hasher->hash(VoucherCode::normalise($validated['code'])))
            ->firstOrFail();

        abort_unless($voucher->isRedeemable() || $voucher->status->isUsable(), 422, 'This voucher cannot accept another device.');

        $device = $binder->bind($voucher, $validated['mac'], 'portal', $validated['label'] ?? null);

        return (new VoucherDeviceResource($device))->response()->setStatusCode(201);
    }
}
