@extends('emails.layout')

@section('content')
<p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #e46f25;">{{ __('emails.delivered.eyebrow') }}</p>
<h2 style="margin: 0 0 20px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 600; color: #2c241c;">{{ __('emails.delivered.heading') }}</h2>
<p style="margin: 0 0 20px 0;">{{ __('emails.greeting', ['name' => $delivery->user->name]) }}</p>
<p style="margin: 0 0 20px 0;">{{ __('emails.delivered.intro') }}</p>
<p style="margin: 0 0 20px 0;">{{ __('emails.delivered.invite_feedback') }}</p>
<p style="margin: 0 0 32px 0;">
    <a href="{{ url('/app/dashboard') }}" style="display: inline-block; padding: 12px 24px; background-color: #2c241c; color: #f1e7d6; text-decoration: none; font-weight: 600; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">{{ __('emails.delivered.cta') }}</a>
</p>
<p style="margin: 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 14px; color: #6b5b4c;">{{ __('emails.delivery_ref', ['id' => $delivery->id]) }}</p>
@endsection
