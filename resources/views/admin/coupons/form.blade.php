{{--
    Shared coupon create/edit form fields.
    Expects: $coupon (Coupon|null), $types (array<CouponType>).
--}}
@php
    $c = $coupon ?? null;
    $fmt = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $dt = fn ($v) => $v?->format('Y-m-d\TH:i');
@endphp

<div x-data="{ type: '{{ old('type', $c?->type->value ?? 'percent') }}' }" class="grid gap-5 sm:grid-cols-2">
    <label class="block">
        <span class="mb-1 block text-xs font-medium text-gray-600">{{ __('coupon.admin.field_code') }}</span>
        <input type="text" name="code" value="{{ old('code', $c?->code) }}" required autocapitalize="characters"
            class="w-full rounded-md border-gray-300 font-mono text-sm uppercase" data-testid="coupon-field-code" />
        <x-input-error :messages="$errors->get('code')" class="mt-1" />
    </label>

    <label class="block">
        <span class="mb-1 block text-xs font-medium text-gray-600">{{ __('coupon.admin.field_label') }}</span>
        <input type="text" name="label" value="{{ old('label', $c?->label) }}"
            class="w-full rounded-md border-gray-300 text-sm" />
        <x-input-error :messages="$errors->get('label')" class="mt-1" />
    </label>

    <label class="block">
        <span class="mb-1 block text-xs font-medium text-gray-600">{{ __('coupon.admin.field_type') }}</span>
        <select name="type" x-model="type" class="w-full rounded-md border-gray-300 text-sm" data-testid="coupon-field-type">
            @foreach ($types as $t)
                <option value="{{ $t->value }}" @selected(old('type', $c?->type->value) === $t->value)>{{ __('coupon.type_' . $t->value) }}</option>
            @endforeach
        </select>
    </label>

    <label class="block">
        <span class="mb-1 block text-xs font-medium text-gray-600">
            <span x-show="type === 'percent'">{{ __('coupon.admin.field_value_percent') }}</span>
            <span x-show="type === 'fixed'" x-cloak>{{ __('coupon.admin.field_value_fixed') }}</span>
        </span>
        <input type="number" step="0.01" min="0" name="value" value="{{ old('value', $fmt($c?->value)) }}" required
            class="w-full rounded-md border-gray-300 text-sm" data-testid="coupon-field-value" />
        <x-input-error :messages="$errors->get('value')" class="mt-1" />
    </label>

    <label class="block" x-show="type === 'percent'" x-cloak>
        <span class="mb-1 block text-xs font-medium text-gray-600">{{ __('coupon.admin.field_max_discount') }}</span>
        <input type="number" step="0.01" min="0" name="max_discount_eur" value="{{ old('max_discount_eur', $fmt($c?->max_discount_eur)) }}"
            class="w-full rounded-md border-gray-300 text-sm" placeholder="{{ __('coupon.admin.optional') }}" />
        <x-input-error :messages="$errors->get('max_discount_eur')" class="mt-1" />
    </label>

    <label class="block">
        <span class="mb-1 block text-xs font-medium text-gray-600">
            {{ __('coupon.admin.field_min_order') }}
            <span x-show="type === 'fixed'" x-cloak class="text-gray-400">({{ __('coupon.admin.required') }})</span>
        </span>
        <input type="number" step="0.01" min="0" name="min_order_eur" value="{{ old('min_order_eur', $fmt($c?->min_order_eur)) }}"
            class="w-full rounded-md border-gray-300 text-sm" data-testid="coupon-field-min-order" />
        <x-input-error :messages="$errors->get('min_order_eur')" class="mt-1" />
    </label>

    <label class="block">
        <span class="mb-1 block text-xs font-medium text-gray-600">{{ __('coupon.admin.field_per_email_limit') }}</span>
        <input type="number" step="1" min="1" name="per_email_limit" value="{{ old('per_email_limit', $c?->per_email_limit ?? 1) }}" required
            class="w-full rounded-md border-gray-300 text-sm" data-testid="coupon-field-per-email" />
        <x-input-error :messages="$errors->get('per_email_limit')" class="mt-1" />
    </label>

    <label class="block">
        <span class="mb-1 block text-xs font-medium text-gray-600">{{ __('coupon.admin.field_max_redemptions') }}</span>
        <input type="number" step="1" min="1" name="max_redemptions" value="{{ old('max_redemptions', $c?->max_redemptions) }}"
            class="w-full rounded-md border-gray-300 text-sm" placeholder="{{ __('coupon.admin.unlimited') }}" data-testid="coupon-field-max-redemptions" />
        <x-input-error :messages="$errors->get('max_redemptions')" class="mt-1" />
    </label>

    <label class="block">
        <span class="mb-1 block text-xs font-medium text-gray-600">{{ __('coupon.admin.field_starts_at') }}</span>
        <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $dt($c?->starts_at)) }}"
            class="w-full rounded-md border-gray-300 text-sm" />
        <x-input-error :messages="$errors->get('starts_at')" class="mt-1" />
    </label>

    <label class="block">
        <span class="mb-1 block text-xs font-medium text-gray-600">{{ __('coupon.admin.field_expires_at') }}</span>
        <input type="datetime-local" name="expires_at" value="{{ old('expires_at', $dt($c?->expires_at)) }}"
            class="w-full rounded-md border-gray-300 text-sm" />
        <x-input-error :messages="$errors->get('expires_at')" class="mt-1" />
    </label>

    <label class="flex items-center gap-2 sm:col-span-2">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $c?->is_active ?? true)) class="rounded border-gray-300 text-emerald-600">
        <span class="text-sm text-gray-700">{{ __('coupon.admin.field_is_active') }}</span>
    </label>
</div>
