@extends('emails.layout')

@section('content')
    <h2 style="margin-top:0; color:#111827;">{{ __('emails.trial_confirmation.heading') }}</h2>

    <p>{{ __('emails.greeting', ['name' => $order->user->name]) }}</p>

    <p>
        {!! __('emails.trial_confirmation.intro', [
            'size' => '<strong>'.e(__(ucfirst($order->box_size))).'</strong>',
            'amount' => number_format((float) $order->amount, 2, ',', '.'),
        ]) !!}
    </p>

    <p>
        {{ __('emails.trial_confirmation.delivery_line', ['days' => $promiseDays]) }}
        @if($order->preferred_delivery_window)
            ({{ __('emails.trial_confirmation.requested_window') }} <em>{{ $order->preferred_delivery_window }}</em>)
        @endif
        {{ __('emails.trial_confirmation.reminder_line') }}
    </p>

    <p style="margin-top:24px;">
        <a href="{{ url('/app/dashboard') }}" style="background:#047857;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;">
            {{ __('emails.trial_confirmation.cta') }}
        </a>
    </p>

    <p style="margin-top:32px;color:#6b7280;font-size:14px;">
        {{ __('emails.order_ref', ['id' => $order->id]) }}
    </p>
@endsection
