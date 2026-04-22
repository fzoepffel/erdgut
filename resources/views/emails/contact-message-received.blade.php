<h1>{{ __('emails.contact_received.heading') }}</h1>

<p><strong>{{ __('emails.contact_received.name') }}:</strong> {{ $contactMessage->name }}</p>
<p><strong>{{ __('emails.contact_received.email') }}:</strong> {{ $contactMessage->email }}</p>
<p><strong>{{ __('emails.contact_received.subject') }}:</strong> {{ $contactMessage->subject ?: __('emails.contact_received.no_subject') }}</p>
<p><strong>{{ __('emails.contact_received.message') }}:</strong></p>
<p>{{ $contactMessage->message }}</p>

<hr>

<p><strong>{{ __('emails.contact_received.ip') }}:</strong> {{ $contactMessage->ip_address ?: __('emails.contact_received.na') }}</p>
<p><strong>{{ __('emails.contact_received.user_agent') }}:</strong> {{ $contactMessage->user_agent ?: __('emails.contact_received.na') }}</p>
<p><strong>{{ __('emails.contact_received.submitted') }}:</strong> {{ $contactMessage->created_at?->toDateTimeString() }}</p>
