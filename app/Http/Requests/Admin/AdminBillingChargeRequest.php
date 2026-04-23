<?php

namespace App\Http\Requests\Admin;

use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class AdminBillingChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subscription_id' => ['required', 'integer', 'exists:subscriptions,id'],
            'week_start_date' => ['required', 'date'],
            'confirm_charge' => ['required', 'accepted'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $week = Carbon::parse($this->input('week_start_date'))->startOfDay();
            if (! $week->isMonday()) {
                $validator->errors()->add(
                    'week_start_date',
                    __('admin.box_planner.validation_week_monday')
                );

                return;
            }

            $subscription = Subscription::query()->find($this->integer('subscription_id'));
            if ($subscription === null) {
                return;
            }

            if ($subscription->status !== SubscriptionStatus::Active) {
                $validator->errors()->add(
                    'subscription_id',
                    __('admin.billing_charge.validation_subscription_not_active')
                );

                return;
            }

            if (! $subscription->hasActiveSumupCredentials()) {
                $validator->errors()->add(
                    'subscription_id',
                    __('admin.billing_charge.validation_missing_payment_method')
                );

                return;
            }

            if ($subscription->isWithinPauseWindow($week)) {
                $validator->errors()->add(
                    'subscription_id',
                    __('admin.billing_charge.validation_subscription_paused')
                );

                return;
            }

            $latestPayment = $subscription->payments()->orderByDesc('attempt_number')->first();
            if ($latestPayment !== null && $latestPayment->status === Payment::STATUS_PENDING) {
                $validator->errors()->add(
                    'subscription_id',
                    __('admin.billing_charge.validation_pending_exists')
                );

                return;
            }

            if (Payment::hasPaidForFulfillmentWeek($subscription->id, $week)) {
                $validator->errors()->add(
                    'week_start_date',
                    __('admin.billing_charge.validation_already_paid_week')
                );
            }
        });
    }
}
