<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Voucher\BatchStatus;
use App\Domain\Voucher\VoucherCode;
use App\Domain\Voucher\VoucherIssuer;
use App\Models\Plan;
use App\Models\Site;
use App\Models\VoucherBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Issues a single voucher for an agent to sell on the spot.
 *
 * The cleartext is returned once in JSON so the agent can show it on screen.
 * Spend is not recorded at generate time: desk cash totals count only when the
 * voucher is redeemed (first_used_at).
 */
class AgentQuickVoucherController
{
    public function __invoke(Request $request, VoucherIssuer $issuer, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isAgent(), 403);

        $sites = $user->sites()->orderBy('name')->get();
        abort_unless($sites->isNotEmpty(), 422, 'You have not been assigned a site yet.');

        $validated = $request->validate([
            'plan_id' => [
                'required',
                'integer',
                Rule::exists(Plan::class, 'id')->where(
                    fn ($query) => $query->where('tenant_id', $user->tenant_id)->where('is_active', true),
                ),
            ],
            'site_id' => ['sometimes', 'integer'],
        ]);

        $siteId = isset($validated['site_id'])
            ? (int) $validated['site_id']
            : (int) $sites->first()->id;
        abort_unless($sites->contains(fn (Site $site) => $site->id === $siteId), 403);

        $plan = Plan::query()->whereKey($validated['plan_id'])->where('is_active', true)->firstOrFail();

        $batch = VoucherBatch::create([
            'plan_id' => $plan->id,
            'site_id' => $siteId,
            'reference' => 'QUICK-'.$user->id.'-'.now()->format('YmdHis').Str::upper(Str::random(3)),
            'quantity' => 1,
            'created_by_user_id' => $user->id,
            'assigned_agent_id' => $user->id,
            'status' => BatchStatus::Generating,
        ]);

        $voucher = $issuer->issueForBatch($batch)->firstOrFail();
        $code = VoucherCode::normalise($voucher->code);

        $batch->forceFill([
            'status' => BatchStatus::Ready,
        ])->save();

        $audit->record('voucher.quick_issued', $voucher, [
            'batch_id' => $batch->id,
            'plan_id' => $plan->id,
            'site_id' => $siteId,
            'code_suffix' => $voucher->code_suffix,
        ], $user);

        return response()->json([
            'code' => $code,
            'display_code' => $code,
            'voucher_id' => $voucher->id,
            'batch_id' => $batch->id,
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'price_minor' => $plan->price_minor,
            ],
        ], 201);
    }
}
