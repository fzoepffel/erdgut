@extends('emails.layout')

@section('content')
    <h2 style="margin-top:0; color:#111827;">{{ __('emails.trial_convert.heading') }}</h2>

    <p>{{ __('emails.greeting', ['name' => $order->user->name]) }}</p>

    <p>
        {{ __('emails.trial_convert.body') }}
    </p>

    <p style="margin-top:24px;">
        <a href="{{ url('/onboard?intent=subscription&from_order=' . $order->id) }}"
           style="background:#047857;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;">
            {{ __('emails.trial_convert.cta') }}
        </a>
    </p>

    <p style="margin-top:32px;color:#6b7280;font-size:14px;">
        {{ __('emails.trial_convert.footnote') }}
    </p>
@endsection
