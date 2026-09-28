<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Billing\TanzanianPhone;
use App\Domain\Tenancy\AuditLogger;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SettingsController
{
    public function show(Request $request): TenantResource
    {
        abort_unless($request->user()?->managesTenant(), 403);

        return new TenantResource($request->user()->tenant);
    }

    public function update(Request $request, AuditLogger $audit): TenantResource
    {
        abort_unless($request->user()?->managesTenant(), 403);

        $validated = $request->validate([
            'portal_name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'support_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'primary_color' => ['sometimes', 'nullable', 'string', 'max:16'],
            'palmpesa_api_token' => ['sometimes', 'nullable', 'string', 'max:255'],
            'palmpesa_user_id' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        if (array_key_exists('support_phone', $validated) && filled($validated['support_phone'])) {
            if (! TanzanianPhone::isValid($validated['support_phone'])) {
                throw ValidationException::withMessages([
                    'support_phone' => 'Weka namba ya simu ya Tanzania, mfano 07XXXXXXXX.',
                ]);
            }

            $validated['support_phone'] = TanzanianPhone::toE164($validated['support_phone']);
        }

        if (array_key_exists('palmpesa_api_token', $validated) && $validated['palmpesa_api_token'] === '') {
            $validated['palmpesa_api_token'] = null;
        }

        /** @var Tenant $tenant */
        $tenant = $request->user()->tenant;
        $tenant->update($validated);

        $audit->record('settings.updated', $tenant, [
            'fields' => array_keys($validated),
        ]);

        return new TenantResource($tenant->fresh());
    }

    public function uploadLogo(Request $request, AuditLogger $audit): TenantResource
    {
        abort_unless($request->user()?->managesTenant(), 403);

        $request->validate([
            'logo' => ['required', 'image', 'max:2048'],
        ]);

        /** @var Tenant $tenant */
        $tenant = $request->user()->tenant;

        if (filled($tenant->logo_path)) {
            Storage::disk('public')->delete($tenant->logo_path);
        }

        $path = $request->file('logo')->store('logos/'.$tenant->uuid, 'public');
        $tenant->update(['logo_path' => $path]);

        $audit->record('settings.logo', $tenant, ['path' => $path]);

        return new TenantResource($tenant->fresh());
    }

    public function destroyLogo(Request $request, AuditLogger $audit): TenantResource
    {
        abort_unless($request->user()?->managesTenant(), 403);

        /** @var Tenant $tenant */
        $tenant = $request->user()->tenant;

        if (filled($tenant->logo_path)) {
            Storage::disk('public')->delete($tenant->logo_path);
            $tenant->update(['logo_path' => null]);
        }

        $audit->record('settings.logo_removed', $tenant);

        return new TenantResource($tenant->fresh());
    }
}
