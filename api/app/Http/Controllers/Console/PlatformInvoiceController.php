<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Tenancy\AuditLogger;
use App\Http\Resources\PlatformInvoiceResource;
use App\Models\PlatformInvoice;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlatformInvoiceController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user, 403);

        if ($user->isPlatformAdmin()) {
            return PlatformInvoiceResource::collection(
                PlatformInvoice::query()->with('tenant')->latest('id')->paginate(50),
            );
        }

        abort_unless($user->managesTenant(), 403);

        return PlatformInvoiceResource::collection(
            PlatformInvoice::query()
                ->where('tenant_id', $user->tenant_id)
                ->latest('id')
                ->paginate(50),
        );
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        abort_unless($request->user()?->isPlatformAdmin(), 403);

        $validated = $request->validate([
            'tenant_uuid' => ['required', 'uuid', 'exists:tenants,uuid'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'period_label' => ['nullable', 'string', 'max:80'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $tenant = Tenant::query()->where('uuid', $validated['tenant_uuid'])->firstOrFail();

        $invoice = PlatformInvoice::create([
            'tenant_id' => $tenant->id,
            'amount_minor' => $validated['amount_minor'],
            'currency' => $validated['currency'] ?? 'TZS',
            'status' => 'issued',
            'period_label' => $validated['period_label'] ?? null,
            'period_start' => $validated['period_start'] ?? null,
            'period_end' => $validated['period_end'] ?? null,
            'due_at' => $validated['due_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $audit->record('platform.invoice_created', $invoice, ['tenant' => $tenant->uuid], $request->user());

        return (new PlatformInvoiceResource($invoice->load('tenant')))->response()->setStatusCode(201);
    }

    public function markPaid(Request $request, PlatformInvoice $invoice, AuditLogger $audit): PlatformInvoiceResource
    {
        abort_unless($request->user()?->isPlatformAdmin(), 403);

        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $audit->record('platform.invoice_paid', $invoice, [], $request->user());

        return new PlatformInvoiceResource($invoice->fresh('tenant'));
    }
}
