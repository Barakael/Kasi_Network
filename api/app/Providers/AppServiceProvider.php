<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Voucher\VoucherCodeHasher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * One tenant per request or job. Resolved by middleware for HTTP, and
         * set explicitly by queued jobs, which carry a tenant id instead.
         */
        $this->app->scoped(CurrentTenant::class);

        $this->app->singleton(
            VoucherCodeHasher::class,
            fn (): VoucherCodeHasher => new VoucherCodeHasher(
                (string) config('kasi.voucher.hash_key'),
            ),
        );
    }

    public function boot(): void
    {
        /*
         * Fail loudly on a missing relationship or attribute rather than
         * silently returning null. In a billing system a typo'd quota field
         * quietly reading as null is the difference between selling a bundle and
         * giving it away.
         */
        Model::shouldBeStrict(! $this->app->isProduction());

        /*
         * Slow queries on the authentication and portal paths are the first
         * symptom of the platform outgrowing its indexes, so they are surfaced
         * rather than left to be noticed by users.
         */
        if (! $this->app->isProduction()) {
            DB::whenQueryingForLongerThan(500, function ($connection, $event): void {
                logger()->warning('Slow query', [
                    'sql' => $event->sql,
                    'time_ms' => $event->time,
                ]);
            });
        }
    }
}
