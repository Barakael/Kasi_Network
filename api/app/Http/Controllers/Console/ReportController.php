<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Billing\CollectionBook;
use App\Domain\Billing\RevenueBook;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReportController
{
    public function revenue(Request $request, RevenueBook $book): JsonResponse
    {
        $from = $request->date('from') ?? now()->subDays(30)->startOfDay();
        $to = $request->date('to') ?? now()->endOfDay();

        return response()->json([
            'data' => $book->seriesBetween($from, $to)->values(),
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

    public function collections(Request $request, CollectionBook $book): JsonResponse
    {
        $user = $request->user();
        $period = $request->string('period')->value() ?: 'day';
        $siteId = $request->integer('site_id') ?: null;
        $agentId = $request->integer('agent_id') ?: null;

        if ($user?->isAgent()) {
            $agentId = $user->id;
            $allowed = $user->sites()->pluck('sites.id');

            if ($siteId) {
                abort_unless($allowed->contains($siteId), 403);
            } elseif ($allowed->count() === 1) {
                $siteId = $allowed->first();
            }
        }

        return response()->json($book->summary($period, $siteId, $agentId));
    }
}
