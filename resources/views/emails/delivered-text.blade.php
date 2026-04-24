{{ __('emails.greeting', ['name' => $delivery->user->name]) }}

{{ __('emails.delivered.heading') }}

{{ __('emails.delivered.intro') }}

{{ __('emails.delivered.invite_feedback') }}

{{ url('/app/dashboard') }}

— {{ __('emails.layout.signature') }}

{{ __('emails.delivery_ref', ['id' => $delivery->id]) }}
