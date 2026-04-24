{{ __('emails.greeting', ['name' => $subscription->user->name]) }}

{{ __('emails.cancellation.heading') }}

{{ __('emails.cancellation.intro') }}
@if($subscription->cancellation_reason)

"{{ $subscription->cancellation_reason }}"
@endif

{{ __('emails.cancellation.comeback_copy') }}

{{ url('/') }}

— {{ __('emails.layout.signature') }}

{{ __('emails.subscription_ref', ['id' => $subscription->id]) }}
