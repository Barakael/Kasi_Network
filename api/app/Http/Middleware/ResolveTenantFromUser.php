<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Tenancy\CurrentTenant;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Scopes a console request to the signed-in user's operator.
 *
 * The tenant comes from the authenticated user, never from the request, so no
 * parameter a client controls can widen what they see.
 *
 * Runs ahead of route model binding, which is the whole reason it resolves the
 * user itself instead of reading $request->user(). Binding is a database read:
 * `/plans/{plan}` loads a plan before any route middleware runs, so a tenant
 * resolved after binding is resolved too late and `Plan::find()` would happily
 * return another operator's row. Scoping first turns that into a 404.
 */
class ResolveTenantFromUser
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->resolveUser($request);

        if ($user === null) {
            // Unauthenticated, or a public route such as login. Whether that is
            // allowed is for the route's own auth middleware to decide.
            return $next($request);
        }

        if (! $user->is_active) {
            abort(403, 'This account has been deactivated.');
        }

        /*
         * Platform administrators have no tenant of their own. They may act
         * inside one by naming it explicitly, which is the only case where a
         * request header decides the tenant.
         */
        if ($user->isPlatformAdmin()) {
            $this->resolveForPlatformAdmin($request);

            return $next($request);
        }

        $tenant = $user->tenant;

        if (! $tenant instanceof Tenant) {
            abort(403, 'This account is not linked to an operator.');
        }

        if (! $tenant->isActive()) {
            abort(403, 'This operator account is suspended.');
        }

        $this->currentTenant->set($tenant);

        return $next($request);
    }

    /**
     * Resolves the caller through the API's own guard.
     *
     * Sanctum's guard tries the session first and then the bearer token, so this
     * covers both a token from the console and a session in tests, without
     * waiting for the route's auth middleware. The guard caches its result, so
     * the later `auth:sanctum` check costs no second lookup.
     */
    private function resolveUser(Request $request): ?User
    {
        $user = Auth::guard('sanctum')->user();

        return $user instanceof User ? $user : null;
    }

    private function resolveForPlatformAdmin(Request $request): void
    {
        $uuid = $request->header('X-Kasi-Tenant');

        if (! is_string($uuid) || $uuid === '') {
            // Left unscoped: platform-wide listings are the point of the role.
            return;
        }

        $tenant = Tenant::query()->where('uuid', $uuid)->first();

        if (! $tenant instanceof Tenant) {
            abort(404, 'Operator not found.');
        }

        $this->currentTenant->set($tenant);
    }
}
