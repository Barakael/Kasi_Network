<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Constrains a model to the operator resolved for the current request.
 *
 * Applied as a global scope rather than left to individual queries: with a dozen
 * tenant-owned tables and a console that lists most of them, a single missing
 * where clause would be a cross-operator data leak. Opt out explicitly with
 * `withoutTenantScope()` or CurrentTenant::withoutTenant().
 *
 * @phpstan-require-extends Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $current = app(CurrentTenant::class);

            if (! $current->shouldScope()) {
                return;
            }

            $builder->where(
                $builder->getModel()->qualifyColumn('tenant_id'),
                $current->id(),
            );
        });

        // Stamps tenant_id on insert so callers never have to remember to.
        static::creating(function ($model): void {
            if ($model->tenant_id !== null) {
                return;
            }

            $tenantId = app(CurrentTenant::class)->id();

            if ($tenantId !== null) {
                $model->tenant_id = $tenantId;
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Escape hatch for platform-level and background queries.
     *
     * @return Builder<static>
     */
    public static function withoutTenantScope(): Builder
    {
        return static::query()->withoutGlobalScope('tenant');
    }
}
