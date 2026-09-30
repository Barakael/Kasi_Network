<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\UserRole;
use App\Models\Plan;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVoucherBatchRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /*
         * The exists rule builds a plain query, so the global tenant scope on
         * these models does not reach it. Every reference is pinned to the
         * current operator by hand; without it, a guessed id would attach another
         * operator's plan to this batch.
         */
        $tenantId = app(CurrentTenant::class)->id();

        return [
            'plan_id' => [
                'required',
                'integer',
                Rule::exists(Plan::class, 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],

            'site_id' => [
                'nullable',
                'integer',
                Rule::exists(Site::class, 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],

            'quantity' => [
                'required',
                'integer',
                'min:1',
                'max:'.config('kasi.voucher.max_batch_quantity'),
            ],

            // Free-text label an operator writes on the printed bundle, e.g.
            // "Kariakoo kiosk - week 38".
            'reference' => ['nullable', 'string', 'max:60'],

            'assigned_agent_id' => [
                'nullable',
                'integer',
                Rule::exists(User::class, 'id')
                    ->where('role', UserRole::Agent->value)
                    ->where('tenant_id', $tenantId),
            ],

            'notes' => ['nullable', 'string', 'max:500'],

            'expires_on' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $agentId = $this->integer('assigned_agent_id') ?: null;
            $siteId = $this->integer('site_id') ?: null;

            if ($agentId === null || $siteId === null) {
                return;
            }

            $assigned = User::query()
                ->where('id', $agentId)
                ->where('role', UserRole::Agent)
                ->whereHas('sites', fn ($q) => $q->where('sites.id', $siteId))
                ->exists();

            if (! $assigned) {
                $validator->errors()->add(
                    'assigned_agent_id',
                    'That agent is not assigned to this site.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assigned_agent_id.exists' => 'That user is not an agent on this account.',
        ];
    }
}
