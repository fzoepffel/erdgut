<?php

namespace App\Http\Requests;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use Illuminate\Foundation\Http\FormRequest;

class SkipDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $delivery = $this->route('delivery');
        if (! $delivery instanceof Delivery) {
            return false;
        }

        if ((int) $delivery->user_id !== (int) $this->user()?->id) {
            return false;
        }

        $baseDelivery = $delivery->is_weekly_override ? $delivery->overridesDelivery : $delivery;
        if (! $baseDelivery instanceof Delivery) {
            return false;
        }

        if ($baseDelivery->scheduled_for_date->toDateString() < now()->toDateString()) {
            return false;
        }

        $effectiveDelivery = $baseDelivery->overrideDeliveries()->latest()->first() ?? $baseDelivery;

        return $effectiveDelivery->status === DeliveryStatus::Scheduled;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
