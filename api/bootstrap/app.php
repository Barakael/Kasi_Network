<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\ResolvePortalContext;
use App\Http\Middleware\ResolveTenantFromUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            /*
             * The captive portal and the Snippe webhook are separate surfaces
             * from the console API: neither is authenticated, and each resolves
             * its tenant differently. Kept in their own files so it stays obvious
             * which routes are reachable without a token.
             */
            Route::middleware('api')
                ->prefix('api/portal')
                ->name('portal.')
                ->group(base_path('routes/portal.php'));

            Route::middleware('api')
                ->prefix('webhooks')
                ->name('webhooks.')
                ->group(base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => ResolveTenantFromUser::class,
            'portal' => ResolvePortalContext::class,
            'role' => EnsureUserRole::class,
        ]);

        /*
         * Every authenticated API request is scoped to the signed-in user's
         * operator before it reaches a controller, so a forgotten where clause
         * cannot leak another operator's data.
         *
         * Prepended, not appended, so it runs ahead of SubstituteBindings. Route
         * model binding reads the database, and a tenant resolved after it would
         * leave `/plans/{plan}` loading any operator's plan by id.
         */
        $middleware->api(prepend: [
            ResolveTenantFromUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->is('webhooks/*')
                || $request->expectsJson(),
        );
    })->create();
