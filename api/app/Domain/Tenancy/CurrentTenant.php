<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Models\Tenant;
use Closure;

/**
 * Holds the operator whose data the current request or job may touch.
 *
 * Registered as a singleton. When a tenant is set, every model using
 * BelongsToTenant is transparently constrained to it, which means a forgotten
 * where clause in a controller cannot leak one operator's vouchers or revenue to
 * another. Background work that legitimately spans operators -- the accounting
 * rollup, quota enforcement -- runs inside withoutTenant().
 */
final class CurrentTenant
{
    private ?Tenant $tenant = null;

    /**
     * Set when scoping is deliberately suspended, so the global scope can tell
     * "no tenant resolved yet" apart from "intentionally unscoped".
     */
    private bool $suspended = false;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function isSet(): bool
    {
        return $this->tenant instanceof Tenant;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    /**
     * Whether the tenant scope should currently be applied to queries.
     */
    public function shouldScope(): bool
    {
        return ! $this->suspended && $this->tenant instanceof Tenant;
    }

    /**
     * Runs a callback with tenant scoping switched off.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function withoutTenant(Closure $callback): mixed
    {
        $previous = $this->suspended;
        $this->suspended = true;

        try {
            return $callback();
        } finally {
            $this->suspended = $previous;
        }
    }

    /**
     * Runs a callback scoped to a specific operator, restoring whatever was set
     * before. Used by queued jobs, which carry a tenant id rather than a request.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function forTenant(Tenant $tenant, Closure $callback): mixed
    {
        $previousTenant = $this->tenant;
        $previousSuspended = $this->suspended;

        $this->tenant = $tenant;
        $this->suspended = false;

        try {
            return $callback();
        } finally {
            $this->tenant = $previousTenant;
            $this->suspended = $previousSuspended;
        }
    }
}
