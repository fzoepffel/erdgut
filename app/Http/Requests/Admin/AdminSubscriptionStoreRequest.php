<?php

namespace App\Http\Requests\Admin;

use App\Enums\BillingMode;
use App\Enums\FulfillmentMode;
use App\Enums\SubscriptionPlan;
use App\Enums\Timeslot;
use App\Support\DeliveryZoneResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminSubscriptionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(DeliveryZoneResolver $zones): array
    {
        $userId = (int) ($this->input('user_id') ?? 0);
        $zoneKey = $zones->resolveZoneKey($userId > 0 ? $userId : null);

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('is_admin', false)),
            ],
            'billing_mode' => ['required', 'string', Rule::enum(BillingMode::class)],
            'plan' => ['required', 'string', Rule::enum(SubscriptionPlan::class)],
            'box_size' => ['required', 'string', Rule::in(['small', 'medium', 'large'])],
            'fulfillment_mode' => ['required', 'string', Rule::enum(FulfillmentMode::class)],
            'delivery_weekday' => [
                'required_if:fulfillment_mode,delivery',
                'nullable',
                'integer',
                'between:0,6',
                Rule::in($zones->allowedWeekdays($zoneKey)),
            ],
            'preferred_timeslot' => [
                'required_if:fulfillment_mode,delivery',
                'nullable',
                'string',
                Rule::in($zones->allowedTimeslots($zoneKey)),
                Rule::enum(Timeslot::class),
            ],
            'preferences' => ['array'],
            'preferences.veg_only' => ['sometimes'],
            'preferences.meat_add_on' => ['sometimes'],
            'preferences.eggs_dairy_add_on' => ['sometimes'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('delivery_weekday')) {
            $this->merge(['delivery_weekday' => (int) $this->input('delivery_weekday')]);
        }

        if ($this->input('preferred_timeslot') === '') {
            $this->merge(['preferred_timeslot' => null]);
        }
    }
}
