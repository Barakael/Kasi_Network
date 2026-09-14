<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * An operator's staff member, owner or counter agent.
 *
 * Deliberately does NOT use the BelongsToTenant global scope. Authentication has
 * to find a user by email before any tenant is known, and a global scope here
 * would make every login fail. Tenant filtering for user lists is applied
 * explicitly through forTenant().
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property string|null $phone
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 */
#[Fillable(['tenant_id', 'name', 'email', 'password', 'role', 'phone', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Voucher batches this agent has been given to print and sell.
     *
     * @return HasMany<VoucherBatch, $this>
     */
    public function assignedBatches(): HasMany
    {
        return $this->hasMany(VoucherBatch::class, 'assigned_agent_id');
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function isPlatformAdmin(): bool
    {
        return $this->role === UserRole::PlatformAdmin;
    }

    public function isAgent(): bool
    {
        return $this->role === UserRole::Agent;
    }

    /**
     * Whether this user may change an operator's configuration: bundles, routers,
     * staff and payment credentials.
     */
    public function managesTenant(): bool
    {
        return $this->role->managesTenant();
    }
}
