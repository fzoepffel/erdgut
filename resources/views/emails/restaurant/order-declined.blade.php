@extends('emails.layout')

@section('content')
    <h2 style="margin: 0 0 12px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 600; color: #2c241c;">
        {{ __('restaurant.order_email.declined_heading') }}
    </h2>
    <p style="margin: 0 0 16px 0; font-size: 15px; color: #2c241c;">
        {{ __('restaurant.order_email.declined_body', ['date' => $order->expected_delivery_date->translatedFormat('d.m.Y')]) }}
    </p>

    @if ($note)
        <p style="margin: 0; padding: 12px; background: #f6f2ea; font-size: 14px; color: #2c241c;">{{ $note }}</p>
    @endif
@endsection
