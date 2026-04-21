@extends('layouts.onboarding', ['step' => 'trial_payment', 'intent' => 'trial'])

@section('content')
    <h1 class="font-display text-3xl font-semibold text-category-produce">{{ __('You are all set!') }}</h1>
    <p class="mt-2 text-sm text-brand-ink/70">{{ __('Your trial box is booked. We will email you the delivery date shortly.') }}</p>

    <dl class="mt-6 space-y-2 text-sm">
        <div class="flex justify-between"><dt>{{ __('Order #') }}</dt><dd class="font-mono">{{ $order->id }}</dd></div>
        <div class="flex justify-between"><dt>{{ __('Box size') }}</dt><dd>{{ ucfirst($order->box_size) }}</dd></div>
        <div class="flex justify-between"><dt>{{ __('Status') }}</dt><dd>{{ $order->status->label() }}</dd></div>
    </dl>

    <div class="mt-8 flex gap-3">
        <a href="{{ route('app.dashboard') }}" class="bg-category-produce text-white px-5 py-2 font-medium hover:bg-category-produce/90">{{ __('Go to dashboard') }}</a>
    </div>
@endsection
