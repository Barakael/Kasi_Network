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

    public function snippet(NasDevice $nas_device, RouterConfigGenerator $generator): JsonResponse
    {
        $this->authorize('view', $nas_device);

        $nas_device->load('site.tenant');

        return response()->json([
            'rsc' => $generator->rsc(
                $nas_device,
                $nas_device->site,
                $nas_device->site->tenant,
                (string) parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'radius.kasi.test',
            ),
            'login_html' => $generator->loginHtml($nas_device->site),
        ]);
    }
}
