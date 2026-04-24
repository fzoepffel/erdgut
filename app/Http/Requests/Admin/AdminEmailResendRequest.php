<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\Admin\AdminEmailResendController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminEmailResendRequest extends FormRequest
{
    /**
     * Known templates admins can re-dispatch. Each maps to a Mailable class +
     * expected entity type in {@see AdminEmailResendController}.
     *
     * @var list<string>
     */
    public const TEMPLATES = [
        'subscription.activated',
        'subscription.skip_confirmation',
        'subscription.cancellation',
        'subscription.pause_exhausted',
        'box.composed',
        'delivery.in_transit',
        'delivery.delivered',
        'order.trial_confirmation',
        'order.trial_delivery_scheduled',
        'order.trial_convert',
    ];

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
            'template' => ['required', 'string', Rule::in(self::TEMPLATES)],
            'subscription_id' => ['nullable', 'integer', 'exists:subscriptions,id'],
            'box_id' => ['nullable', 'integer', 'exists:boxes,id'],
            'delivery_id' => ['nullable', 'integer', 'exists:deliveries,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ];
    }
}
