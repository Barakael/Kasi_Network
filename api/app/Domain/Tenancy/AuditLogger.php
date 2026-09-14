<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Records operator actions worth being able to reconstruct after the fact:
 * who issued a batch, who disabled a voucher, who cut a session off.
 */
final readonly class AuditLogger
{
    /**
     * Context keys that must never be written, even if a caller passes them.
     *
     * Voucher codes and RADIUS secrets are credentials; the audit trail is read
     * far more widely than the tables they normally live in.
     *
     * @var array<int, string>
     */
    private const array REDACTED_KEYS = [
        'code',
        'codes',
        'password',
        'secret',
        'shared_secret',
        'api_key',
        'api_password',
        'snippe_api_key',
        'snippe_webhook_secret',
        'webhook_secret',
    ];

    public function __construct(
        private CurrentTenant $currentTenant,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @param  User|null  $actor  Overrides the request user. Needed when the actor
     *                            is not yet authenticated on the request, as
     *                            during sign-in, where the request user is still
     *                            null at the point the event is recorded.
     */
    public function record(
        string $action,
        ?Model $subject = null,
        array $context = [],
        ?User $actor = null,
    ): AuditLog {
        $actor ??= $this->request->user();

        return AuditLog::query()->create([
            // Falls back to the actor's operator, since the tenant scope is
            // resolved from the authenticated user and so is also unset at login.
            'tenant_id' => $this->currentTenant->id() ?? $actor?->tenant_id,
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $subject === null ? null : $subject::class,
            'auditable_id' => $subject?->getKey(),
            'context' => $context === [] ? null : $this->redact($context),
            'ip_address' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 255),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACTED_KEYS, true)) {
                $context[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $context[$key] = $this->redact($value);
            }
        }

        return $context;
    }
}
