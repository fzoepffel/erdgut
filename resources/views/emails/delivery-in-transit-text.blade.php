{{ __('emails.greeting', ['name' => $delivery->user->name]) }}

{{ __('emails.delivery_in_transit.heading') }}

{{ __('emails.delivery_in_transit.intro', [
    'date' => $delivery->scheduled_for_date?->translatedFormat('l, j. F') ?? '',
]) }}@if($delivery->timeslot) ({{ $delivery->timeslot }})@endif


{{ __('emails.delivery_in_transit.followup') }}

{{ url('/app/dashboard') }}

— {{ __('emails.layout.signature') }}

{{ __('emails.delivery_ref', ['id' => $delivery->id]) }}
