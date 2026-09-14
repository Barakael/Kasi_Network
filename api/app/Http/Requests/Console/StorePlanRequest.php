<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use App\Domain\Voucher\BillingPeriod;
use App\Domain\Voucher\QuotaAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation for creating a bundle.
 *
 * Bundles are the product, so the rules here are the difference between a
 * coherent price list and one that sells access nobody can use. The awkward parts
 * are the interactions between fields, which are checked in withValidator: a
 * throttled plan with no throttle speed, or a duration longer than the window it
 * has to be spent in, both validate field by field and are still nonsense.
 */
class StorePlanRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'billing_period' => ['required', Rule::enum(BillingPeriod::class)],

            /*
             * Only required for a custom period; the presets supply their own.
             * Capped at a year: a longer window is almost certainly a units
             * mistake, and vouchers valid indefinitely are a liability.
             */
            'validity_seconds' => [
                'required_if:billing_period,custom',
                'nullable',
                'integer',
                'min:300',
                'max:31536000',
            ],

            /*
             * Metered online time, separate from the wall-clock window. A daily
             * bundle capped at two hours of use is a common shape, so this is
             * independent of validity rather than derived from it.
             */
            'duration_seconds' => ['nullable', 'integer', 'min:60', 'max:31536000'],

            // Null means uncapped. Minimum of a megabyte, since anything smaller
            // is spent before the client finishes loading a page.
            'data_cap_bytes' => ['nullable', 'integer', 'min:1048576'],

            // Zero is allowed: free bundles are used for testing and for
            // complimentary access in hotels and cafes.
            'price_minor' => ['required', 'integer', 'min:0', 'max:100000000'],

            'rate_limit_down_kbps' => ['nullable', 'integer', 'min:64', 'max:1000000'],
            'rate_limit_up_kbps' => ['nullable', 'integer', 'min:64', 'max:1000000'],

            // Above a handful this stops being a voucher and starts being a
            // site-wide account, which is a different product.
            'device_limit' => ['required', 'integer', 'min:1', 'max:20'],

            'on_quota_exhausted' => ['required', Rule::enum(QuotaAction::class)],
            'throttle_down_kbps' => ['nullable', 'integer', 'min:32', 'max:1000000'],
            'throttle_up_kbps' => ['nullable', 'integer', 'min:32', 'max:1000000'],

            // How long an unsold printed card stays redeemable. Bounds the window
            // in which a stolen batch is worth anything.
            'shelf_life_days' => ['nullable', 'integer', 'min:1', 'max:1095'],

            'is_active' => ['boolean'],
            'is_sold_online' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:1000'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateThrottleIsUsable($validator);
            $this->validateDurationFitsValidity($validator);
            $this->validateOnlineSaleHasPrice($validator);
        });
    }

    /**
     * A plan that throttles instead of disconnecting needs a speed to throttle
     * to. Without one the client keeps full speed after the cap and the bundle
     * is effectively uncapped.
     */
    private function validateThrottleIsUsable(Validator $validator): void
    {
        if ($this->enumValue('on_quota_exhausted') !== QuotaAction::Throttle) {
            return;
        }

        if ($this->integerOrNull('throttle_down_kbps') === null) {
            $validator->errors()->add(
                'throttle_down_kbps',
                'A throttled bundle needs a reduced download speed to fall back to.',
            );
        }

        /*
         * Throttling above the plan's own speed would raise it, which is not what
         * anyone means by throttle.
         */
        $rate = $this->integerOrNull('rate_limit_down_kbps');
        $throttle = $this->integerOrNull('throttle_down_kbps');

        if ($rate !== null && $throttle !== null && $throttle >= $rate) {
            $validator->errors()->add(
                'throttle_down_kbps',
                'The throttled speed must be slower than the bundle speed.',
            );
        }
    }

    /**
     * Metered time longer than the window it sits in cannot be spent, so the
     * client is sold time they will never get.
     */
    private function validateDurationFitsValidity(Validator $validator): void
    {
        $duration = $this->integerOrNull('duration_seconds');

        if ($duration === null) {
            return;
        }

        $validity = $this->resolvedValiditySeconds();

        if ($validity !== null && $duration > $validity) {
            $validator->errors()->add(
                'duration_seconds',
                'Online time cannot exceed how long the bundle stays valid.',
            );
        }
    }

    /**
     * Mobile money cannot collect nothing, so a free bundle offered online would
     * produce orders that can never be paid.
     */
    private function validateOnlineSaleHasPrice(Validator $validator): void
    {
        if ($this->boolean('is_sold_online') && (int) $this->input('price_minor') === 0) {
            $validator->errors()->add(
                'is_sold_online',
                'A free bundle cannot be sold online; distribute it as vouchers instead.',
            );
        }
    }

    /**
     * The validity this request will actually produce, whether stated or implied
     * by the chosen period.
     */
    public function resolvedValiditySeconds(): ?int
    {
        $period = $this->enumValue('billing_period');

        return $this->integerOrNull('validity_seconds')
            ?? $period?->validitySeconds();
    }

    /**
     * The attributes to persist, with the period's implied validity filled in.
     *
     * @return array<string, mixed>
     */
    public function planAttributes(): array
    {
        return [
            ...$this->safe()->except(['validity_seconds']),
            'validity_seconds' => $this->resolvedValiditySeconds(),
        ];
    }

    protected function enumValue(string $key): BillingPeriod|QuotaAction|null
    {
        $value = $this->input($key);

        if (! is_string($value)) {
            return null;
        }

        return $key === 'billing_period'
            ? BillingPeriod::tryFrom($value)
            : QuotaAction::tryFrom($value);
    }

    protected function integerOrNull(string $key): ?int
    {
        $value = $this->input($key);

        return $value === null || $value === '' ? null : (int) $value;
    }
}
