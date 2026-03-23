@extends('emails.layout')

@section('content')
<p style="margin: 0 0 16px 0;">{{ __('Hallo,') }}</p>
<p style="margin: 0 0 16px 0;">{{ __('Die Änderungsfrist für Ihre Box der Woche :week endet morgen.', ['week' => $weekStart]) }}</p>
<p style="margin: 0 0 16px 0;">{{ __('Letzter Termin für Änderungen: :deadline', ['deadline' => $deadline]) }}</p>
<p style="margin: 0 0 24px 0;">
    <a href="{{ $editUrl }}" style="display: inline-block; padding: 12px 24px; background-color: #047857; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600;">{{ __('Box bearbeiten') }}</a>
</p>
<p style="margin: 0; color: #6b7280; font-size: 14px;">{{ __('Nach Ablauf der Frist wird Ihre Box für die Lieferung gesperrt.') }}</p>
@endsection
