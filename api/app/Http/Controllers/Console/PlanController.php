<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Tenancy\AuditLogger;
use App\Http\Requests\Console\StorePlanRequest;
use App\Http\Requests\Console\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * Bundle management.
 *
 * Plans are the price list. Editing one never changes vouchers already issued
 * from it, because each voucher carries its own copy of the terms; that is why a
 * price correction here is safe and why deleting a plan does not invalidate
 * outstanding cards.
 */
class PlanController
{
    use AuthorizesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Plan::class);

        $plans = Plan::query()
            ->when(
                $request->boolean('active_only'),
                fn ($query) => $query->where('is_active', true),
            )
            ->withCount([
                // Shown in the console so an operator can see at a glance which
                // bundles are actually selling.
                'vouchers',
            ])
            ->orderBy('sort_order')
            ->orderBy('price_minor')
            ->get();

        return PlanResource::collection($plans);
    }

    public function store(StorePlanRequest $request, AuditLogger $audit): JsonResponse
    {
        $this->authorize('create', Plan::class);

        $plan = Plan::create($request->planAttributes());

        $audit->record('plan.created', $plan, ['name' => $plan->name]);

        return (new PlanResource($plan))->response()->setStatusCode(201);
    }

    public function show(Plan $plan): PlanResource
    {
        $this->authorize('view', $plan);

        return new PlanResource($plan);
    }

    public function update(UpdatePlanRequest $request, Plan $plan, AuditLogger $audit): PlanResource
    {
        $this->authorize('update', $plan);

        $plan->update($request->planAttributes());

        $audit->record('plan.updated', $plan, ['changes' => array_keys($plan->getChanges())]);

        return new PlanResource($plan->refresh());
    }

    public function offer(Request $request, Plan $plan, AuditLogger $audit): PlanResource
    {
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'price_minor' => ['required', 'integer', 'min:0', 'max:100000000'],
            'offer_label' => ['required', 'string', 'max:80'],
            'offer_ends_at' => ['required', 'date', 'after:now'],
        ]);

        $plan->applyOffer(
            $validated['price_minor'],
            $validated['offer_label'],
            Carbon::parse($validated['offer_ends_at']),
        );

        $audit->record('plan.offer', $plan, ['label' => $validated['offer_label']]);

        return new PlanResource($plan->fresh());
    }

    public function restorePrice(Request $request, Plan $plan, AuditLogger $audit): PlanResource
    {
        $this->authorize('update', $plan);

        $plan->restoreDefaultPrice();
        $audit->record('plan.restore_price', $plan);

        return new PlanResource($plan->fresh());
    }

    /**
     * Retires a bundle.
     *
     * Soft deleted rather than removed, because vouchers and orders reference it
     * and reports would lose the name of what was sold. Outstanding vouchers keep
     * working: their terms were snapshotted at issue.
     */
    public function destroy(Plan $plan, AuditLogger $audit): JsonResponse
    {
        $this->authorize('delete', $plan);

        $plan->delete();

        $audit->record('plan.deleted', $plan, ['name' => $plan->name]);

        return response()->json(['message' => 'Bundle retired.']);
    }
}
