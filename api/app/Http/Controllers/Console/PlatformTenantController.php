<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\UserRole;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlatformTenantController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->isPlatformAdmin(), 403);

        return TenantResource::collection(
            Tenant::query()->withCount('users')->orderBy('name')->get(),
        );
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        abort_unless($request->user()?->isPlatformAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'portal_name' => ['nullable', 'string', 'max:80'],
            'support_phone' => ['nullable', 'string', 'max:20'],
            'admin_name' => ['required', 'string', 'max:80'],
            'admin_email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
        ]);

        $tenant = Tenant::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(4)),
            'code_prefix' => Tenant::suggestCodePrefix($validated['name']),
            'status' => 'active',
            'currency' => 'TZS',
            'timezone' => 'Africa/Dar_es_Salaam',
            'portal_name' => $validated['portal_name'] ?? $validated['name'],
            'support_phone' => $validated['support_phone'] ?? null,
        ]);

        $plain = $validated['password'] ?? Str::password(12, symbols: false);

        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['admin_name'],
            'email' => $validated['admin_email'],
            'password' => Hash::make($plain),
            'role' => UserRole::Owner,
            'is_active' => true,
        ]);

        $audit->record('platform.tenant_created', $tenant, ['admin' => $admin->email], $request->user());

        return (new TenantResource($tenant))
            ->additional([
                'admin' => (new UserResource($admin))->resolve(),
                'password' => $plain,
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Tenant $tenant, AuditLogger $audit): TenantResource
    {
        abort_unless($request->user()?->isPlatformAdmin(), 403);

        $validated = $request->validate([
            'status' => ['sometimes', 'in:active,suspended'],
            'name' => ['sometimes', 'string', 'max:120'],
            'portal_name' => ['sometimes', 'nullable', 'string', 'max:80'],
        ]);

        $tenant->update($validated);

        $audit->record('platform.tenant_updated', $tenant, $validated, $request->user());

        return new TenantResource($tenant->fresh());
    }
}
