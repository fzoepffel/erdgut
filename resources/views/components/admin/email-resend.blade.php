@props([
    'context' => 'subscription', // subscription | box | delivery | order
    'entityId' => null,
    'templates' => [], // list of template keys keyed by template -> label; if empty, sensible defaults by context
])

@php
    $defaults = [
        'subscription' => [
            'subscription.activated' => __('admin.operations.emails.resend.subscription_activated'),
            'subscription.skip_confirmation' => __('admin.operations.emails.resend.subscription_skip_confirmation'),
            'subscription.cancellation' => __('admin.operations.emails.resend.subscription_cancellation'),
            'subscription.pause_exhausted' => __('admin.operations.emails.resend.subscription_pause_exhausted'),
        ],
        'box' => [
            'box.composed' => __('admin.operations.emails.resend.box_composed'),
        ],
        'delivery' => [
            'delivery.in_transit' => __('admin.operations.emails.resend.delivery_in_transit'),
            'delivery.delivered' => __('admin.operations.emails.resend.delivery_delivered'),
        ],
        'order' => [
            'order.trial_confirmation' => __('admin.operations.emails.resend.order_trial_confirmation'),
            'order.trial_delivery_scheduled' => __('admin.operations.emails.resend.order_trial_delivery_scheduled'),
            'order.trial_convert' => __('admin.operations.emails.resend.order_trial_convert'),
        ],
    ];
    $options = count($templates) > 0 ? $templates : ($defaults[$context] ?? []);
    $entityField = match ($context) {
        'subscription' => 'subscription_id',
        'box' => 'box_id',
        'delivery' => 'delivery_id',
        'order' => 'order_id',
        default => 'subscription_id',
    };
@endphp

@if ($entityId && count($options) > 0)
    <form method="POST" action="{{ route('app.admin.emails.resend') }}" class="flex flex-wrap items-end gap-2" onsubmit="return confirm('{{ __('admin.operations.emails.resend.confirm') }}');" data-testid="admin-email-resend-form">
        @csrf
        <input type="hidden" name="{{ $entityField }}" value="{{ $entityId }}" />
        <div>
            <label for="admin-email-resend-template-{{ $context }}-{{ $entityId }}" class="mb-1 block text-xs font-medium text-gray-600">
                {{ __('admin.operations.emails.resend.label') }}
            </label>
            <select
                id="admin-email-resend-template-{{ $context }}-{{ $entityId }}"
                name="template"
                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:w-64"
            >
                @foreach ($options as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" data-testid="admin-email-resend-submit" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
            {{ __('admin.operations.emails.resend.submit') }}
        </button>
    </form>
@endif
