@extends('emails.layout')

@section('content')
<p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #e46f25;">{{ __('emails.cancellation.eyebrow') }}</p>
<h2 style="margin: 0 0 20px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 600; color: #2c241c;">{{ __('emails.cancellation.heading') }}</h2>
<p style="margin: 0 0 20px 0;">{{ __('emails.greeting', ['name' => $subscription->user->name]) }}</p>
<p style="margin: 0 0 20px 0;">{{ __('emails.cancellation.intro') }}</p>
@if($subscription->cancellation_reason)
<p style="margin: 0 0 20px 0; padding: 12px 16px; background-color: rgba(44, 36, 28, 0.04); border-left: 3px solid #c53a2b; font-family: Georgia, 'Times New Roman', serif; font-style: italic; color: #6b5b4c;">
    &ldquo;{{ $subscription->cancellation_reason }}&rdquo;
</p>
@endif
<p style="margin: 0 0 20px 0;">{{ __('emails.cancellation.comeback_copy') }}</p>
<p style="margin: 0 0 32px 0;">
    <a href="{{ url('/') }}" style="display: inline-block; padding: 12px 24px; background-color: #2c241c; color: #f1e7d6; text-decoration: none; font-weight: 600; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">{{ __('emails.cancellation.cta') }}</a>
</p>
<p style="margin: 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 14px; color: #6b5b4c;">{{ __('emails.subscription_ref', ['id' => $subscription->id]) }}</p>
@endsection
