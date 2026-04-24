<?php

namespace App\Http\Requests\Admin;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdminDeliveryUpdateRequest extends FormRequest
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
        /** @var Delivery $delivery */
        $delivery = $this->route('delivery');
        $userId = (int) $delivery->user_id;

        return [
            'address_id' => [
                'required',
                'integer',
                Rule::exists('addresses', 'id')->where('user_id', $userId),
            ],
            'scheduled_for_date' => ['required', 'date'],
            'timeslot' => ['required', 'string', Rule::in(Delivery::allowedTimeslots())],
            'status' => ['required', 'string', Rule::in(array_map(
                static fn (DeliveryStatus $s): string => $s->value,
                DeliveryStatus::cases()
            ))],
            'special_instructions' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Delivery $delivery */
            $delivery = $this->route('delivery');

            if ($delivery->is_weekly_override) {
                return;
            }

            $scheduledFor = (string) $this->input('scheduled_for_date');
            $duplicate = Delivery::query()
                ->where('user_id', $delivery->user_id)
                ->where('is_weekly_override', false)
                ->whereDate('scheduled_for_date', $scheduledFor)
                ->where('id', '!=', $delivery->id)
                ->exists();

            if ($duplicate) {
                $validator->errors()->add(
                    'scheduled_for_date',
                    __('admin.deliveries.validation.canonical_duplicate')
                );
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function deliveryAttributes(): array
    {
        return [
            'address_id' => (int) $this->input('address_id'),
            'scheduled_for_date' => $this->input('scheduled_for_date'),
            'timeslot' => $this->input('timeslot'),
            'status' => DeliveryStatus::from($this->input('status')),
            'special_instructions' => $this->input('special_instructions'),
        ];
    }
}
