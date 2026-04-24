{{ __('emails.greeting', ['name' => $subscription->user->name]) }}

{{ __('emails.subscription_activated.heading') }}

{{ __('emails.subscription_activated.intro', [
    'size' => __(ucfirst($subscription->box_size)),
    'plan' => __($subscription->plan->value),
]) }}
@if($subscription->next_charge_date)

{{ __('emails.subscription_activated.next_charge', [
    'date' => \Illuminate\Support\Carbon::parse($subscription->next_charge_date)->translatedFormat('l, j. F Y'),
]) }}
@endif

{{ url('/app/dashboard') }}

— {{ __('emails.layout.signature') }}

{{ __('emails.subscription_ref', ['id' => $subscription->id]) }}
