@extends('emails.layout')

@section('content')
    <h2 style="margin-top:0; color:#111827;">Keep Erdgut coming every week?</h2>

    <p>Hi {{ $order->user->name }},</p>

    <p>
        Hope you enjoyed your trial box! If you'd like the same experience every week, you can
        turn your trial into a subscription in under a minute — we already have your address and
        preferences on file.
    </p>

    <p style="margin-top:24px;">
        <a href="{{ url('/onboard?intent=subscription&from_order=' . $order->id) }}"
           style="background:#047857;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;">
            Start my subscription
        </a>
    </p>

    <p style="margin-top:32px;color:#6b7280;font-size:14px;">
        Cancel anytime. No long commitment.
    </p>
@endsection
