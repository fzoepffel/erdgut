<h1>New contact message received</h1>

<p><strong>Name:</strong> {{ $contactMessage->name }}</p>
<p><strong>Email:</strong> {{ $contactMessage->email }}</p>
<p><strong>Subject:</strong> {{ $contactMessage->subject ?: 'No subject' }}</p>
<p><strong>Message:</strong></p>
<p>{{ $contactMessage->message }}</p>

<hr>

<p><strong>IP:</strong> {{ $contactMessage->ip_address ?: 'n/a' }}</p>
<p><strong>User-Agent:</strong> {{ $contactMessage->user_agent ?: 'n/a' }}</p>
<p><strong>Submitted:</strong> {{ $contactMessage->created_at?->toDateTimeString() }}</p>
