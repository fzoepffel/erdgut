<?php

namespace App\Http\Requests\Admin;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\Admin\WeeklyBoxPlannerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class AdminBoxStoreRequest extends FormRequest
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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $subscription = Subscription::query()->find($this->integer('subscription_id'));
            if ($subscription === null) {
                return;
            }

            if ($subscription->status !== SubscriptionStatus::Active) {
                $validator->errors()->add(
                    'subscription_id',
                    __('admin.box_planner.validation_subscription_not_active')
                );

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

            $planner = app(WeeklyBoxPlannerService::class);
            if (! $planner->isEligibleForWeek($subscription, $week)) {
                $validator->errors()->add(
                    'week_start_date',
                    __('admin.box_planner.validation_not_eligible_week')
                );
            }
        });
    }
}
