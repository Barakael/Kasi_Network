<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Radius\RadiusProvisioner;
use App\Domain\Tenancy\AuditLogger;
use App\Domain\Voucher\BatchStatus;
use App\Domain\Voucher\VoucherStatus;
use App\Http\Requests\Console\StoreVoucherBatchRequest;
use App\Http\Resources\VoucherBatchResource;
use App\Http\Resources\VoucherResource;
use App\Jobs\IssueVoucherBatch;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Voucher batch management.
 *
 * A batch is a print run: one plan, a quantity, and optionally the agent it will
 * be handed to. Creating one queues the code generation rather than doing it
 * inline, because a ten-thousand-voucher run is not something to hold an HTTP
 * request open for.
 */
class VoucherBatchController
{
    use AuthorizesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', VoucherBatch::class);

        $batches = VoucherBatch::query()
            ->with(['plan', 'site', 'assignedAgent'])
            ->withCount(VoucherBatchResource::countsFor())
            /*
             * An agent's list is narrowed to their own stock. The policy blocks
             * them from opening someone else's batch, but without this they would
             * still see that it exists and how it is selling.
             */
            ->when(
                $request->user()->isAgent(),
                fn (Builder $query) => $query->where('assigned_agent_id', $request->user()->id),
            )
            ->when(
                $request->filled('status'),
                fn (Builder $query) => $query->where('status', $request->string('status')),
            )
            ->when(
                $request->filled('plan_id'),
                fn (Builder $query) => $query->where('plan_id', $request->integer('plan_id')),
            )
            ->latest('id')
            ->paginate($request->integer('per_page', 25));

        return VoucherBatchResource::collection($batches);
    }

    public function store(StoreVoucherBatchRequest $request, AuditLogger $audit): JsonResponse
    {
        $this->authorize('create', VoucherBatch::class);

        $batch = VoucherBatch::create([
            ...$request->validated(),
            'reference' => $request->string('reference')->value()
                ?: 'B-'.Str::upper(Str::random(6)),
            'created_by_user_id' => $request->user()->id,
            'status' => BatchStatus::Generating,
        ]);

        /*
         * Small print packs finish in this request so the operator can print
         * without a queue worker. Large runs still go to Redis.
         */
        if ($batch->quantity <= (int) config('kasi.voucher.sync_issue_max')) {
            IssueVoucherBatch::dispatchSync($batch);
        } else {
            IssueVoucherBatch::dispatch($batch);
        }

        $audit->record('voucher_batch.created', $batch, [
            'quantity' => $batch->quantity,
            'plan_id' => $batch->plan_id,
        ]);

        $batch->refresh();

        return (new VoucherBatchResource($batch->load('plan')->loadCount(VoucherBatchResource::countsFor())))
            ->response()
            ->setStatusCode($batch->status === BatchStatus::Ready ? 201 : 202);
    }

    public function show(VoucherBatch $batch): VoucherBatchResource
    {
        $this->authorize('view', $batch);

        return new VoucherBatchResource(
            $batch->load(['plan', 'site', 'assignedAgent', 'createdBy'])
                ->loadCount(VoucherBatchResource::countsFor()),
        );
    }

    /**
     * The vouchers in a batch, without their codes.
     */
    public function vouchers(Request $request, VoucherBatch $batch): AnonymousResourceCollection
    {
        $this->authorize('view', $batch);

        $vouchers = $batch->vouchers()
            ->with('usage')
            ->when(
                $request->filled('status'),
                fn (Builder $query) => $query->where('status', $request->string('status')),
            )
            ->orderBy('id')
            ->paginate($request->integer('per_page', 50));

        return VoucherResource::collection($vouchers);
    }

    /**
     * Reassigns a batch to a different agent, or records that it has been handed
     * over.
     */
    public function update(Request $request, VoucherBatch $batch, AuditLogger $audit): VoucherBatchResource
    {
        $this->authorize('update', $batch);

        $validated = $request->validate([
            'reference' => ['sometimes', 'string', 'max:60'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'assigned_agent_id' => ['sometimes', 'nullable', 'integer'],
            'status' => ['sometimes', 'in:ready,distributed'],
        ]);

        if (array_key_exists('assigned_agent_id', $validated) && $validated['assigned_agent_id'] !== null) {
            $request->validate([
                'assigned_agent_id' => (new StoreVoucherBatchRequest)->rules()['assigned_agent_id'],
            ]);
        }

        $batch->update($validated);

        $audit->record('voucher_batch.updated', $batch, ['changes' => array_keys($batch->getChanges())]);

        return new VoucherBatchResource(
            $batch->load(['plan', 'assignedAgent'])->loadCount(VoucherBatchResource::countsFor()),
        );
    }

    /**
     * Withdraws a whole batch, for instance when printed stock is reported stolen.
     *
     * Every unused code has its RADIUS authorisation deleted, so the cards stop
     * working at the router. Codes already redeemed are left alone: the customer
     * paid, and cutting them off punishes the wrong person. Sessions currently
     * running continue until they end, since removing authorisation only affects
     * the next authentication.
     */
    public function disable(Request $request, VoucherBatch $batch, RadiusProvisioner $provisioner, AuditLogger $audit): JsonResponse
    {
        $this->authorize('disable', $batch);

        $reason = $request->string('reason')->value() ?: 'Batch withdrawn';
        $userId = $request->user()->id;
        $disabled = 0;

        DB::transaction(function () use ($batch, $provisioner, $reason, $userId, &$disabled): void {
            $batch->vouchers()
                ->where('status', VoucherStatus::Unused)
                ->chunkById(500, function ($vouchers) use ($provisioner, $reason, $userId, &$disabled): void {
                    $provisioner->revokeUsernames($vouchers->pluck('code')->all());

                    Voucher::withoutTenantScope()
                        ->whereIn('id', $vouchers->pluck('id'))
                        ->update([
                            'status' => VoucherStatus::Disabled,
                            'disabled_at' => now(),
                            'disabled_by_user_id' => $userId,
                            'disable_reason' => $reason,
                        ]);

                    $disabled += $vouchers->count();
                });

            $batch->update(['status' => BatchStatus::Disabled]);
        });

        $audit->record('voucher_batch.disabled', $batch, ['vouchers_disabled' => $disabled]);

        return response()->json([
            'message' => "Batch withdrawn; {$disabled} unused vouchers disabled.",
            'vouchers_disabled' => $disabled,
        ]);
    }
}
