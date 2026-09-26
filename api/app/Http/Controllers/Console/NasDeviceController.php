<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Radius\NasSynchroniser;
use App\Domain\Router\RouterConfigGenerator;
use App\Domain\Tenancy\AuditLogger;
use App\Http\Resources\NasDeviceResource;
use App\Models\NasDevice;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class NasDeviceController
{
    use AuthorizesRequests;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', NasDevice::class);

        return NasDeviceResource::collection(
            NasDevice::query()->with('site')->orderBy('name')->get(),
        );
    }

    public function store(Request $request, NasSynchroniser $sync, AuditLogger $audit): JsonResponse
    {
        $this->authorize('create', NasDevice::class);

        $validated = $request->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'name' => ['required', 'string', 'max:80'],
            'nasname' => ['required', 'ip'],
            'shared_secret' => ['nullable', 'string', 'min:16', 'max:60'],
            'coa_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'api_host' => ['nullable', 'string', 'max:128'],
            'api_port' => ['nullable', 'integer'],
            'api_username' => ['nullable', 'string', 'max:64'],
            'api_password' => ['nullable', 'string', 'max:128'],
            'api_uses_tls' => ['boolean'],
        ]);

        $device = NasDevice::create([
            ...$validated,
            'shared_secret' => $validated['shared_secret'] ?? Str::password(24, symbols: false),
            'coa_port' => $validated['coa_port'] ?? 3799,
            'status' => 'active',
        ]);

        $sync->sync($device);
        $audit->record('nas.created', $device, ['nasname' => $device->nasname]);

        return (new NasDeviceResource($device->load('site')))->response()->setStatusCode(201);
    }

    public function update(Request $request, NasDevice $nas_device, NasSynchroniser $sync, AuditLogger $audit): NasDeviceResource
    {
        $this->authorize('update', $nas_device);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'nasname' => ['sometimes', 'ip'],
            'shared_secret' => ['sometimes', 'string', 'min:16', 'max:60'],
            'coa_port' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'api_host' => ['sometimes', 'nullable', 'string', 'max:128'],
            'api_port' => ['sometimes', 'nullable', 'integer'],
            'api_username' => ['sometimes', 'nullable', 'string', 'max:64'],
            'api_password' => ['sometimes', 'nullable', 'string', 'max:128'],
            'status' => ['sometimes', 'in:active,disabled'],
        ]);

        $previousNasname = $nas_device->nasname;
        $nas_device->update($validated);

        if ($nas_device->wasChanged('nasname') || $nas_device->wasChanged('shared_secret') || $nas_device->wasChanged('name')) {
            $sync->resync($nas_device, $previousNasname);
        }

        $audit->record('nas.updated', $nas_device, ['changes' => array_keys($nas_device->getChanges())]);

        return new NasDeviceResource($nas_device->load('site'));
    }

    public function snippet(NasDevice $nas_device, RouterConfigGenerator $generator): JsonResponse
    {
        $this->authorize('view', $nas_device);

        $nas_device->load('site.tenant');

        return response()->json([
            'rsc' => $generator->rsc(
                $nas_device,
                $nas_device->site,
                $nas_device->site->tenant,
                (string) config('kasi.radius.public_host'),
            ),
            'login_html' => $generator->loginHtml($nas_device->site),
        ]);
    }
}
