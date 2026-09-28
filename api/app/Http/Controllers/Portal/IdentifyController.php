<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Customers\CustomerDirectory;
use App\Domain\Tenancy\PortalContext;
use App\Domain\Tenancy\PortalPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IdentifyController
{
    public function __invoke(
        Request $request,
        PortalContext $context,
        CustomerDirectory $directory,
        PortalPayload $payload,
    ): JsonResponse {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $customer = $directory->identify($context, $validated['phone']);
        $identified = $context->withCustomer($customer->id);

        return $payload->json($identified, $context->nasDevice);
    }
}
