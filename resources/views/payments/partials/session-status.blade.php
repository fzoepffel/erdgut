@if (session('status') === 'payment-already-setup')
    <p class="mb-4 border border-category-fish/40 bg-category-fish/5 px-4 py-3 text-sm text-brand-ink" role="status">
        {{ __('Payment method already set up.') }}
    </p>
@elseif (session('status') === 'payment-updated')
    <p class="mb-4 border border-category-produce/40 bg-category-produce/5 px-4 py-3 text-sm text-brand-ink" role="status">
        {{ __('Payment method updated successfully.') }}
    </p>
@elseif (session('status') === 'payment-update-unavailable')
    <p class="mb-4 border border-brand-ink/20 bg-brand-ink/[0.03] px-4 py-3 text-sm text-brand-ink" role="alert">
        {{ __('Update payment method is not available. Set up a payment method first.') }}
    </p>
@elseif (session('status') === 'payment-setup-success')
    <p class="mb-4 border border-category-produce/40 bg-category-produce/5 px-4 py-3 text-sm text-brand-ink" role="status">
        {{ __('Payment method set up successfully.') }}
    </p>
@elseif (session('status') === 'payment-setup-failed')
    <p class="mb-4 border border-category-meat/40 bg-category-meat/5 px-4 py-3 text-sm text-brand-ink" role="alert">
        {{ __('Payment setup failed. Please try again.') }}
    </p>
@elseif (session('status') === 'payment-setup-unavailable')
    <p class="mb-4 border border-brand-ink/20 bg-brand-ink/[0.03] px-4 py-3 text-sm text-brand-ink" role="alert">
        {{ __('Payment setup is temporarily unavailable. Please try again later.') }}
    </p>
@endif
