<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Billing\TanzanianPhone;
use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\UserRole;
use App\Http\Resources\UserResource;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StaffController
{
    public function agents(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->managesTenant(), 403);

        return UserResource::collection(
            User::query()
                ->forTenant((int) app(CurrentTenant::class)->id())
                ->where('role', UserRole::Agent)
                ->with('sites')
                ->orderBy('name')
                ->get(),
        );
    }

    public function storeAgent(Request $request, AuditLogger $audit): JsonResponse
    {
        abort_unless($request->user()?->managesTenant(), 403);

        $tenantId = (int) app(CurrentTenant::class)->id();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
            'site_ids' => ['nullable', 'array'],
            'site_ids.*' => [
                'integer',
                Rule::exists(Site::class, 'id')->where('tenant_id', $tenantId),
            ],
        ]);

        if (filled($validated['phone'] ?? null) && TanzanianPhone::isValid($validated['phone'])) {
            $validated['phone'] = TanzanianPhone::toE164($validated['phone']);
        }

        $plain = $validated['password'] ?? Str::password(12, symbols: false);

        $agent = User::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($plain),
            'role' => UserRole::Agent,
            'is_active' => true,
        ]);

        $agent->sites()->sync($validated['site_ids'] ?? []);

        $audit->record('agent.created', $agent, ['email' => $agent->email]);

        return (new UserResource($agent->load('sites')))
            ->additional(['password' => $validated['password'] ?? $plain])
            ->response()
            ->setStatusCode(201);
    }

    public function updateAgent(Request $request, User $agent, AuditLogger $audit): UserResource
    {
        abort_unless($request->user()?->managesTenant(), 403);
        abort_unless($agent->isAgent() && $agent->tenant_id === $request->user()->tenant_id, 404);

        $tenantId = (int) app(CurrentTenant::class)->id();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
            'site_ids' => ['sometimes', 'array'],
            'site_ids.*' => [
                'integer',
                Rule::exists(Site::class, 'id')->where('tenant_id', $tenantId),
            ],
        ]);

        if (array_key_exists('phone', $validated) && filled($validated['phone']) && TanzanianPhone::isValid($validated['phone'])) {
            $validated['phone'] = TanzanianPhone::toE164($validated['phone']);
        }

        $agent->update(collect($validated)->except('site_ids')->all());

        if (array_key_exists('site_ids', $validated)) {
            $agent->sites()->sync($validated['site_ids']);
        }

        $audit->record('agent.updated', $agent, ['fields' => array_keys($validated)]);

        return new UserResource($agent->fresh('sites'));
    }
}
