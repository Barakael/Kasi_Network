<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Tenancy\AuditLogger;
use App\Http\Resources\SiteResource;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class SiteController
{
    public function index(): AnonymousResourceCollection
    {
        return SiteResource::collection(
            Site::query()->withCount('nasDevices')->orderBy('name')->get(),
        );
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        abort_unless($request->user()?->managesTenant(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'ssid' => ['nullable', 'string', 'max:32'],
            'nas_identifier' => ['required', 'string', 'max:64', 'unique:sites,nas_identifier'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $site = Site::create([
            ...$validated,
            'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(4)),
            'status' => 'active',
            'timezone' => $validated['timezone'] ?? 'Africa/Dar_es_Salaam',
        ]);

        $audit->record('site.created', $site, ['name' => $site->name]);

        return (new SiteResource($site))->response()->setStatusCode(201);
    }
}
