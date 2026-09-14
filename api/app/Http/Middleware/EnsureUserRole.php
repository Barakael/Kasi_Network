<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to specific roles.
 *
 *   Route::get('/reports', ...)->middleware('role:owner,staff');
 *
 * Coarse gate only. Anything that depends on which records a user may touch --
 * an agent seeing just their own batches -- is a policy, not this.
 */
class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        // Platform administrators are implicitly permitted everywhere.
        if ($user->isPlatformAdmin()) {
            return $next($request);
        }

        if (! in_array($user->role->value, $roles, true)) {
            abort(403, 'Your account does not have access to this.');
        }

        return $next($request);
    }
}
