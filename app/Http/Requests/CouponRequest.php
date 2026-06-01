<?php

namespace App\Http\Requests;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates the admin coupon create/update form.
 *
 * Enforces the two product rules that keep coupons safe: a percent coupon's
 * rate must stay strictly below 100 (and under the configured max) so an
 * order can never go free, and a fixed coupon must carry a `min_order_eur`
 * so a small order can't be zeroed out. The code is unique (case-insensitive
 * via the normalized column) excluding the row being edited.
 */
class CouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is already admin-gated by middleware.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('code')) {
            $merge['code'] = Coupon::normalizeCode((string) $this->input('code'));
        }

        // Unchecked checkbox ⇒ absent; coerce to a concrete boolean.
        $merge['is_active'] = $this->boolean('is_active');

        // Blank optional numerics arrive as '' from the form — null them so
        // the "nullable" rules and DB columns behave.
        foreach (['max_discount_eur', 'min_order_eur', 'max_redemptions', 'starts_at', 'expires_at', 'label'] as $field) {
            if ($this->input($field) === '') {
                $merge[$field] = null;
            }
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Coupon|null $coupon */
        $coupon = $this->route('coupon');
        $maxPercent = (float) config('coupon.max_percent', 90);

        return [
            'code' => [
                'required', 'string', 'max:64', 'regex:/^[A-Z0-9._-]+$/',
                Rule::unique('coupons', 'code')->ignore($coupon?->id),
            ],
            'label' => ['nullable', 'string', 'max:120'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => ['required', 'numeric', 'gt:0'],
            'max_discount_eur' => ['nullable', 'numeric', 'gt:0'],
            'min_order_eur' => ['nullable', 'numeric', 'gt:0'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'per_email_limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $type = $this->input('type');
            $value = (float) $this->input('value');
            $maxPercent = (float) config('coupon.max_percent', 90);

            if ($type === CouponType::Percent->value) {
                // No 100%-off — a percentage discount can never zero an order.
                if ($value > $maxPercent) {
                    $v->errors()->add('value', __('coupon.admin.errors.percent_too_high', ['max' => (int) $maxPercent]));
                }
            }

            if ($type === CouponType::Fixed->value) {
                // A fixed voucher must require a minimum order so it can't go free.
                $min = $this->input('min_order_eur');
                if ($min === null || (float) $min <= 0) {
                    $v->errors()->add('min_order_eur', __('coupon.admin.errors.fixed_needs_min'));
                } elseif ((float) $min < $value) {
                    // The minimum order must at least cover the voucher value.
                    $v->errors()->add('min_order_eur', __('coupon.admin.errors.min_below_value'));
                }
            }
        });
    }
}
