@extends('layouts.onboarding', ['step' => 'trial_payment', 'intent' => 'trial'])

@section('content')
    <p class="eyebrow text-chapter-produce">{{ __('Booked') }}</p>
    <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">{{ __('You are all set!') }}</h1>
    <p class="mt-3 font-editorial text-base italic leading-relaxed text-brand-ink/70">
        {{ __('Your trial box is booked. We will email you the delivery date shortly.') }}
    </p>

    <dl class="mt-6 border border-brand-ink/15 bg-brand-ink/[0.02] p-5 text-sm text-brand-ink/80">
        <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
            <dt class="eyebrow text-brand-ink/60">{{ __('Order #') }}</dt>
            <dd class="font-mono text-brand-ink">{{ $order->id }}</dd>
        </div>
        <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
            <dt class="eyebrow text-brand-ink/60">{{ __('Box size') }}</dt>
            <dd class="text-brand-ink">{{ __(ucfirst($order->box_size)) }}</dd>
        </div>
        <div class="flex items-center justify-between gap-4 py-2">
            <dt class="eyebrow text-brand-ink/60">{{ __('Status') }}</dt>
            <dd class="text-brand-ink">{{ $order->status->label() }}</dd>
        </div>
    </dl>

    <div class="mt-8 flex flex-col items-start gap-6">
        <a href="{{ route('app.dashboard') }}" class="btn-solid">{{ __('Go to dashboard') }}</a>
        <div class="flex flex-col items-start gap-2">
            <x-triple-star class="opacity-70" />
            <p class="font-editorial text-sm italic text-brand-ink/60">
                {{ __('A seat at the table — yours, from next delivery.') }}
            </p>
        </div>
    </div>
@endsection
