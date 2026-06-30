@extends('emails.layout')

@section('content')
    <h2 style="margin: 0 0 12px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 600; color: #2c241c;">
        {{ __('restaurant.email.rejected_heading') }}
    </h2>
    <p style="margin: 0 0 16px 0; font-size: 15px; color: #2c241c;">
        {{ __('restaurant.email.rejected_body', ['business' => $businessName]) }}
    </p>
    @if ($reason)
        <p style="margin: 0; font-size: 14px; color: #6b5b4c;">{{ $reason }}</p>
    @endif
@endsection
