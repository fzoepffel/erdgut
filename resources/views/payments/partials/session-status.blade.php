@if (session('status') === 'payment-already-setup')
    <p class="mb-4 rounded-md bg-sky-50 px-3 py-2 text-sm text-sky-800" role="status">
        {{ __('Payment method already set up.') }}
    </p>
@elseif (session('status') === 'payment-updated')
    <p class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">
        {{ __('Payment method updated successfully.') }}
    </p>
@elseif (session('status') === 'payment-update-unavailable')
    <p class="mb-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800" role="alert">
        {{ __('Update payment method is not available. Set up a payment method first.') }}
    </p>
@elseif (session('status') === 'payment-setup-success')
    <p class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">
        {{ __('Payment method set up successfully.') }}
    </p>
@elseif (session('status') === 'payment-setup-failed')
    <p class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
        {{ __('Payment setup failed. Please try again.') }}
    </p>
@elseif (session('status') === 'payment-setup-unavailable')
    <p class="mb-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800" role="alert">
        {{ __('Payment setup is temporarily unavailable. Please try again later.') }}
    </p>
@endif
