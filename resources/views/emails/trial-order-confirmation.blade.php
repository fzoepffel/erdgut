@extends('emails.layout')

@section('content')
<p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #e46f25;">{{ __('emails.trial_confirmation.eyebrow') }}</p>
<h2 style="margin: 0 0 20px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 600; color: #2c241c;">{{ __('emails.trial_confirmation.heading') }}</h2>
<p style="margin: 0 0 20px 0;">{{ __('emails.greeting', ['name' => $order->user->name]) }}</p>
<p style="margin: 0 0 20px 0;">
    {!! __('emails.trial_confirmation.intro', [
        'size' => '<strong>'.e(__(ucfirst($order->box_size))).'</strong>',
        'amount' => number_format((float) $order->amount, 2, ',', '.'),
    ]) !!}
</p>
<p style="margin: 0 0 20px 0;">
    {{ __('emails.trial_confirmation.delivery_line', ['days' => $promiseDays]) }}
    @if($order->preferred_delivery_window)
        ({{ __('emails.trial_confirmation.requested_window') }} <em>{{ $order->preferred_delivery_window }}</em>)
    @endif
    {{ __('emails.trial_confirmation.reminder_line') }}
</p>
<p style="margin: 0 0 32px 0;">
    <a href="{{ url('/app/dashboard') }}" style="display: inline-block; padding: 12px 24px; background-color: #2c241c; color: #f1e7d6; text-decoration: none; font-weight: 600; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">{{ __('emails.trial_confirmation.cta') }}</a>
</p>
<p style="margin: 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 14px; color: #6b5b4c;">{{ __('emails.order_ref', ['id' => $order->id]) }}</p>
@endsection
