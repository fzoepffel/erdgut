@extends('emails.layout')

@section('content')
<p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #1d4485;">{{ __('emails.trial_scheduled.eyebrow') }}</p>
<h2 style="margin: 0 0 20px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 600; color: #2c241c;">{{ __('emails.trial_scheduled.heading') }}</h2>
<p style="margin: 0 0 20px 0;">{{ __('emails.greeting', ['name' => $order->user->name]) }}</p>
<p style="margin: 0 0 20px 0;">
    {!! __('emails.trial_scheduled.intro', [
        'date' => '<strong>'.e(optional($order->scheduled_for_date)->format('l, d F Y')).'</strong>',
    ]) !!}
    @if($order->preferred_timeslot)
        {!! __('emails.trial_scheduled.between', [
            'slot' => '<strong>'.e($order->preferred_timeslot).'</strong>',
        ]) !!}
    @endif.
</p>
<p style="margin: 0 0 24px 0;">{{ __('emails.trial_scheduled.reminder_line') }}</p>
<p style="margin: 0 0 32px 0;">
    <a href="{{ url('/app/dashboard') }}" style="display: inline-block; padding: 12px 24px; background-color: #2c241c; color: #f1e7d6; text-decoration: none; font-weight: 600; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">{{ __('emails.trial_scheduled.cta') }}</a>
</p>
<p style="margin: 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 14px; color: #6b5b4c;">{{ __('emails.order_ref', ['id' => $order->id]) }}</p>
@endsection
