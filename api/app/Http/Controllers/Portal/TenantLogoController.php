<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantLogoController
{
    public function __invoke(string $tenant): StreamedResponse
    {
        $operator = Tenant::query()->where('uuid', $tenant)->firstOrFail();

        abort_unless(filled($operator->logo_path) && Storage::disk('public')->exists($operator->logo_path), 404);

        return Storage::disk('public')->response($operator->logo_path);
    }
}
