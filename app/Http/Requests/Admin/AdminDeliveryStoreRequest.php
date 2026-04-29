<?php

namespace App\Http\Requests\Admin;

use App\Enums\DeliveryStatus;
use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdminDeliveryStoreRequest extends FormRequest
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
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'address_id' => [
                'required',
                'integer',
                Rule::exists('addresses', 'id')->where(fn ($q) => $q->where('user_id', (int) $this->input('user_id'))),
            ],
            'scheduled_for_date' => ['required', 'date'],
            'timeslot' => ['required', 'string', Rule::in(Delivery::allowedTimeslots())],
            'status' => ['required', 'string', Rule::in(array_map(
                static fn (DeliveryStatus $s): string => $s->value,
                DeliveryStatus::cases()
            ))],
            'special_instructions' => ['nullable', 'string', 'max:2000'],
            'is_weekly_override' => ['sometimes', 'boolean'],
            'overrides_delivery_id' => [
                'nullable',
                'integer',
                Rule::exists('deliveries', 'id')
                    ->where(fn ($q) => $q->where('user_id', (int) $this->input('user_id'))->where('is_weekly_override', false)),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $userId = (int) $this->input('user_id');
            $isOverride = $this->boolean('is_weekly_override');
            $scheduledFor = (string) $this->input('scheduled_for_date');
            $overridesId = $this->input('overrides_delivery_id');

            if ($isOverride) {
                if (empty($overridesId)) {
                    $validator->errors()->add(
                        'overrides_delivery_id',
                        __('admin.deliveries.validation.override_requires_base')
                    );

                    return;
                }

                $existingOverride = Delivery::query()
                    ->where('is_weekly_override', true)
                    ->where('overrides_delivery_id', (int) $overridesId)
                    ->exists();

                if ($existingOverride) {
                    $validator->errors()->add(
                        'overrides_delivery_id',
                        __('admin.deliveries.validation.override_already_exists')
                    );
                }

                return;
            }

            $duplicate = Delivery::query()
                ->where('user_id', $userId)
                ->where('is_weekly_override', false)
                ->whereDate('scheduled_for_date', $scheduledFor)
                ->exists();

            if ($duplicate) {
                $validator->errors()->add(
                    'scheduled_for_date',
                    __('admin.deliveries.validation.canonical_duplicate')
                );

                return;
            }

            // Canonical deliveries must have something physical behind them:
            // either a Box for the user in the ISO week of `scheduled_for_date`,
            // or a paid/scheduled trial Order for the user with a matching
            // `scheduled_for_date`. No more orphans.
            $weekStart = Carbon::parse($scheduledFor)->startOfWeek(Carbon::MONDAY);
            $hasBox = Box::query()
                ->where('user_id', $userId)
                ->whereDate('week_start_date', $weekStart->toDateString())
                ->exists();
            $hasTrialOrder = Order::query()
                ->where('user_id', $userId)
                ->where('product_type', OrderProductType::TrialBox->value)
                ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Scheduled->value])
                ->whereDate('scheduled_for_date', $scheduledFor)
                ->exists();

            if (! $hasBox && ! $hasTrialOrder) {
                $validator->errors()->add(
                    'user_id',
                    __('admin.deliveries.validation.no_box_or_order')
                );
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function deliveryAttributes(): array
    {
        $user = User::query()->findOrFail((int) $this->input('user_id'));
        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', SubscriptionStatus::Cancelled)
            ->orderByDesc('updated_at')
            ->first();

        $isOverride = $this->boolean('is_weekly_override');

        // Auto-link the canonical delivery to whatever's actually shipping —
        // the validator already proved one exists. Overrides are explicitly
        // detached: they reference the base delivery via overrides_delivery_id
        // so the source link stays on the base row.
        $boxId = null;
        $orderId = null;
        if (! $isOverride) {
            $weekStart = Carbon::parse((string) $this->input('scheduled_for_date'))->startOfWeek(Carbon::MONDAY);
            $box = Box::query()
                ->where('user_id', $user->id)
                ->whereDate('week_start_date', $weekStart->toDateString())
                ->orderByDesc('id')
                ->first();
            $boxId = $box?->id;

            $order = Order::query()
                ->where('user_id', $user->id)
                ->where('product_type', OrderProductType::TrialBox->value)
                ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Scheduled->value])
                ->whereDate('scheduled_for_date', (string) $this->input('scheduled_for_date'))
                ->orderByDesc('id')
                ->first();
            $orderId = $order?->id;
        }

        return [
            'user_id' => (int) $this->input('user_id'),
            'address_id' => (int) $this->input('address_id'),
            'box_id' => $boxId,
            'order_id' => $orderId,
            'scheduled_for_date' => $this->input('scheduled_for_date'),
            'timeslot' => $this->input('timeslot'),
            'status' => DeliveryStatus::from($this->input('status')),
            'special_instructions' => $this->input('special_instructions'),
            'is_weekly_override' => $isOverride,
            'overrides_delivery_id' => $isOverride
                ? (int) $this->input('overrides_delivery_id')
                : null,
            'is_test' => (bool) ($subscription?->is_test ?? $user->is_test),
        ];
    }
}
