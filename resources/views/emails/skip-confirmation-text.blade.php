{{ __('emails.greeting', ['name' => $subscription->user->name]) }}

{{ __('emails.skip_confirmation.heading') }}

@if(count($skipWeekStartDates) === 0)
{{ __('emails.skip_confirmation.empty') }}
@else
{{ __('emails.skip_confirmation.intro') }}
@foreach($skipWeekStartDates as $weekStart)
- {{ \Illuminate\Support\Carbon::parse($weekStart)->translatedFormat('l, j. F Y') }}
@endforeach
@endif

{{ __('emails.skip_confirmation.resume_copy') }}

{{ url('/app/dashboard') }}

— {{ __('emails.layout.signature') }}

{{ __('emails.subscription_ref', ['id' => $subscription->id]) }}
