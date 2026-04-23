@extends('emails.layout')

@section('content')
<p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #21341d;">{{ __('This week in the box') }}</p>
<h2 style="margin: 0 0 20px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 600; color: #2c241c;">{{ __('The deadline to adjust is near.') }}</h2>
<p style="margin: 0 0 16px 0;">{{ __('Hallo,') }}</p>
<p style="margin: 0 0 16px 0;">{{ __('Die Änderungsfrist für Ihre Box der Woche :week endet morgen.', ['week' => $weekStart]) }}</p>
<p style="margin: 0 0 24px 0;">{{ __('Letzter Termin für Änderungen: :deadline', ['deadline' => $deadline]) }}</p>
<p style="margin: 0 0 32px 0;">
    <a href="{{ $editUrl }}" style="display: inline-block; padding: 12px 24px; background-color: #2c241c; color: #f1e7d6; text-decoration: none; font-weight: 600; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">{{ __('Box bearbeiten') }}</a>
</p>
<p style="margin: 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 14px; color: #6b5b4c;">{{ __('Nach Ablauf der Frist wird Ihre Box für die Lieferung gesperrt.') }}</p>
@endsection
