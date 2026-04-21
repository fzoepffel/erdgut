@extends('emails.layout')

@section('content')
    <h2 style="margin-top:0; color:#111827;">Your trial box is confirmed</h2>

    <p>Hi {{ $order->user->name }},</p>

    <p>
        Thanks for trying Erdgut! We've received your payment for a <strong>{{ ucfirst($order->box_size) }}</strong>
        trial box (EUR {{ number_format((float) $order->amount, 2, ',', '.') }}, one-time, no subscription).
    </p>

    <p>
        We'll confirm your exact delivery date within {{ $promiseDays }} days
        @if($order->preferred_delivery_window)
            (you asked for: <em>{{ $order->preferred_delivery_window }}</em>)
        @endif
        and send a reminder before we knock on your door.
    </p>

    <p style="margin-top:24px;">
        <a href="{{ url('/app/dashboard') }}" style="background:#047857;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;">
            View my order
        </a>
    </p>

    <p style="margin-top:32px;color:#6b7280;font-size:14px;">
        Order #{{ $order->id }}
    </p>
@endsection
