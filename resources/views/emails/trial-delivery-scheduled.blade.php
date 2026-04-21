@extends('emails.layout')

@section('content')
    <h2 style="margin-top:0; color:#111827;">Your delivery date is set</h2>

    <p>Hi {{ $order->user->name }},</p>

    <p>
        Good news — your trial box will arrive on
        <strong>{{ optional($order->scheduled_for_date)->format('l, d F Y') }}</strong>
        @if($order->preferred_timeslot)
            between <strong>{{ $order->preferred_timeslot }}</strong>
        @endif.
    </p>

    <p>
        We'll send a short reminder the day before. If you need to change anything, just reply to this email.
    </p>

    <p style="margin-top:24px;">
        <a href="{{ url('/app/dashboard') }}" style="background:#047857;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;">
            View order details
        </a>
    </p>

    <p style="margin-top:32px;color:#6b7280;font-size:14px;">
        Order #{{ $order->id }}
    </p>
@endsection
