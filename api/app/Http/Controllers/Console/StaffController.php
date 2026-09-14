<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\UserRole;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StaffController
{
    public function agents(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->managesTenant(), 403);

        return UserResource::collection(
            User::query()
                ->forTenant((int) app(CurrentTenant::class)->id())
                ->where('role', UserRole::Agent)
                ->orderBy('name')
                ->get(),
        );
    }
}
