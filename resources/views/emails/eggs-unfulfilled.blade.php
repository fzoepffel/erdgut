@extends('emails.layout')

@section('content')
<p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #e46f25;">{{ __('emails.eggs_unfulfilled.eyebrow') }}</p>
<h2 style="margin: 0 0 20px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 600; color: #2c241c;">{{ __('emails.eggs_unfulfilled.heading') }}</h2>
<p style="margin: 0 0 20px 0;">{{ __('emails.greeting', ['name' => $user->name]) }}</p>
<p style="margin: 0 0 20px 0;">{{ __('emails.eggs_unfulfilled.intro', ['date' => $box->week_start_date?->translatedFormat('d.m.Y')]) }}</p>
<p style="margin: 0 0 20px 0;">{{ __('emails.eggs_unfulfilled.refund_line', ['amount' => \App\Support\Money::format($refundAmountEur)]) }}</p>
<p style="margin: 0 0 32px 0;">{{ __('emails.eggs_unfulfilled.outro') }}</p>
<p style="margin: 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 14px; color: #6b5b4c;">{{ __('emails.eggs_unfulfilled.signoff') }}</p>
@endsection
