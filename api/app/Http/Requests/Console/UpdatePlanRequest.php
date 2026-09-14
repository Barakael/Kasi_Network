<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use App\Domain\Voucher\BillingPeriod;
use App\Domain\Voucher\QuotaAction;
use App\Models\Plan;

/**
 * Validation for editing a bundle.
 *
 * Identical rules to creation, applied to a partial payload. Editing a plan does
 * not change vouchers already issued from it -- their terms were copied at issue
 * time -- so an operator correcting a price is not silently rewriting what people
 * already bought.
 */
class UpdatePlanRequest extends StorePlanRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return collect(parent::rules())
            ->map(function (array $rules): array {
                /*
                 * `sometimes` lets the console PATCH a single field. Required
                 * rules stay in place for fields that are present, so a payload
                 * cannot blank out a bundle's price by sending null.
                 */
                return ['sometimes', ...$rules];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function planAttributes(): array
    {
        $attributes = $this->safe()->except(['validity_seconds']);

        /*
         * Validity is only recalculated when the request actually touches it.
         * Rebuilding it on every edit would reset a custom window back to its
         * period's default the first time someone changed the plan's name.
         */
        if ($this->has('validity_seconds') || $this->has('billing_period')) {
            $validity = $this->resolvedValiditySeconds();

            if ($validity !== null) {
                $attributes['validity_seconds'] = $validity;
            }
        }

        return $attributes;
    }

    /**
     * Cross-field checks run against the bundle as it will end up, not just the
     * fields that were sent. Otherwise removing a throttle speed from a throttled
     * bundle passes validation, because the rule looks for a quota action that
     * this request never mentioned.
     */
    protected function enumValue(string $key): BillingPeriod|QuotaAction|null
    {
        return $this->has($key)
            ? parent::enumValue($key)
            : $this->plan()?->{$key};
    }

    protected function integerOrNull(string $key): ?int
    {
        if ($this->has($key)) {
            return parent::integerOrNull($key);
        }

        $existing = $this->plan()?->{$key};

        return $existing === null ? null : (int) $existing;
    }

    private function plan(): ?Plan
    {
        $plan = $this->route('plan');

        return $plan instanceof Plan ? $plan : null;
    }
}
