{{ __('emails.greeting', ['name' => $subscription->user->name]) }}

{{ __('emails.pause_exhausted.heading') }}

{{ __('emails.pause_exhausted.intro') }}

{{ __('emails.pause_exhausted.next_step') }}

{{ url('/app/dashboard') }}

— {{ __('emails.layout.signature') }}

{{ __('emails.subscription_ref', ['id' => $subscription->id]) }}
