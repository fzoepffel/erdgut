@extends('emails.layout')

@section('content')
<p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #e46f25;">{{ __('emails.skip_confirmation.eyebrow') }}</p>
<h2 style="margin: 0 0 20px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 600; color: #2c241c;">{{ __('emails.skip_confirmation.heading') }}</h2>
<p style="margin: 0 0 20px 0;">{{ __('emails.greeting', ['name' => $subscription->user->name]) }}</p>
@if(count($skipWeekStartDates) === 0)
<p style="margin: 0 0 20px 0;">{{ __('emails.skip_confirmation.empty') }}</p>
@else
<p style="margin: 0 0 12px 0;">{{ __('emails.skip_confirmation.intro') }}</p>
<ul style="margin: 0 0 20px 0; padding-left: 20px;">
    @foreach($skipWeekStartDates as $weekStart)
        <li style="margin-bottom: 4px;">{{ \Illuminate\Support\Carbon::parse($weekStart)->translatedFormat('l, j. F Y') }}</li>
    @endforeach
</ul>
@endif
<p style="margin: 0 0 20px 0;">{{ __('emails.skip_confirmation.resume_copy') }}</p>
<p style="margin: 0 0 32px 0;">
    <a href="{{ url('/app/dashboard') }}" style="display: inline-block; padding: 12px 24px; background-color: #2c241c; color: #f1e7d6; text-decoration: none; font-weight: 600; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">{{ __('emails.skip_confirmation.cta') }}</a>
</p>
<p style="margin: 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 14px; color: #6b5b4c;">{{ __('emails.subscription_ref', ['id' => $subscription->id]) }}</p>
@endsection
