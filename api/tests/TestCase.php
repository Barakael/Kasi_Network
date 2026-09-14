<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\Tenancy\CurrentTenant;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Puts the container in the state middleware would leave it in for a request
     * from this operator, so code under test sees the same tenant scope it would
     * in production.
     */
    protected function actingForTenant(Tenant $tenant): void
    {
        app(CurrentTenant::class)->set($tenant);
    }
}
