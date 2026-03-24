<?php

namespace App\Http\Requests\Admin;

use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\Subscription;
use App\Services\Admin\WeeklyBoxPlannerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class AdminBoxPresetRequest extends FormRequest
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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $box = $this->route('box');
            if (! $box instanceof Box) {
                return;
            }

            $subscription = Subscription::query()->find($this->integer('subscription_id'));
            if ($subscription === null) {
                return;
            }

            if ($subscription->user_id !== $box->user_id) {
                $validator->errors()->add(
                    'subscription_id',
                    __('admin.box_planner.validation_subscription_mismatch')
                );

                return;
            }

            if ($subscription->status !== SubscriptionStatus::Active) {
                $validator->errors()->add(
                    'subscription_id',
                    __('admin.box_planner.validation_subscription_not_active')
                );

                return;
            }

            $week = Carbon::parse($box->week_start_date)->startOfDay();
            $planner = app(WeeklyBoxPlannerService::class);
            if (! $planner->isEligibleForWeek($subscription, $week)) {
                $validator->errors()->add(
                    'subscription_id',
                    __('admin.box_planner.validation_not_eligible_week')
                );
            }
        });
    }
}
