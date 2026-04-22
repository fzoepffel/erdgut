@extends('emails.layout')

@section('content')
    <h2 style="margin-top:0; color:#111827;">{{ __('emails.trial_scheduled.heading') }}</h2>

    <p>{{ __('emails.greeting', ['name' => $order->user->name]) }}</p>

    <p>
        {!! __('emails.trial_scheduled.intro', [
            'date' => '<strong>'.e(optional($order->scheduled_for_date)->format('l, d F Y')).'</strong>',
        ]) !!}
        @if($order->preferred_timeslot)
            {!! __('emails.trial_scheduled.between', [
                'slot' => '<strong>'.e($order->preferred_timeslot).'</strong>',
            ]) !!}
        @endif.
    </p>

    <p>
        {{ __('emails.trial_scheduled.reminder_line') }}
    </p>

    <p style="margin-top:24px;">
        <a href="{{ url('/app/dashboard') }}" style="background:#047857;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;">
            {{ __('emails.trial_scheduled.cta') }}
        </a>
    </p>

    <p style="margin-top:32px;color:#6b7280;font-size:14px;">
        {{ __('emails.order_ref', ['id' => $order->id]) }}
    </p>
@endsection
