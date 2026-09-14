<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Billing\OrderStatus;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReportController
{
    public function revenue(Request $request): JsonResponse
    {
        $from = $request->date('from') ?? now()->subDays(30)->startOfDay();
        $to = $request->date('to') ?? now()->endOfDay();

        $rows = Order::query()
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Fulfilled])
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('DATE(paid_at) as day, COUNT(*) as orders, SUM(COALESCE(net_minor, amount_minor)) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return response()->json([
            'data' => $rows,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    public function orders(): AnonymousResourceCollection
    {
        return OrderResource::collection(
            Order::query()->with('plan')->latest('id')->paginate(50),
        );
    }
}
