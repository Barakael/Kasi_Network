<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Voucher\MacBinder;
use App\Http\Resources\VoucherDeviceResource;
use App\Models\Voucher;
use App\Models\VoucherDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VoucherDeviceController
{
    public function index(): AnonymousResourceCollection
    {
        return VoucherDeviceResource::collection(
            VoucherDevice::query()->with('voucher')->latest('id')->paginate(50),
        );
    }

    public function store(Request $request, MacBinder $binder): JsonResponse
    {
        abort_unless($request->user()?->managesTenant(), 403);

        $validated = $request->validate([
            'voucher_id' => ['required', 'integer'],
            'mac' => ['required', 'string', 'max:32'],
            'label' => ['nullable', 'string', 'max:80'],
        ]);

        $voucher = Voucher::query()->findOrFail($validated['voucher_id']);
        $device = $binder->bind($voucher, $validated['mac'], 'console', $validated['label'] ?? null);

        return (new VoucherDeviceResource($device))->response()->setStatusCode(201);
    }

    public function destroy(VoucherDevice $voucher_device, MacBinder $binder): JsonResponse
    {
        abort_unless(request()->user()?->managesTenant(), 403);
        $binder->revoke($voucher_device);

        return response()->json(['message' => 'Device binding revoked.']);
    }
}
