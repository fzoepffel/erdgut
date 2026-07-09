@extends('emails.layout')

@php
    $rowStyle = 'padding: 6px 0; border-bottom: 1px solid rgba(44, 36, 28, 0.08); font-size: 14px;';
    $labelStyle = 'color: #6b5b4c; font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; padding-right: 12px; vertical-align: top; white-space: nowrap;';
    $valueStyle = 'color: #2c241c; vertical-align: top;';
@endphp

@section('content')
    <p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: #c53a2b;">
        Restaurant — Anfrage prüfen
    </p>
    <h2 style="margin: 0 0 8px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 600; color: #2c241c;">
        Neue Restaurant-Anfrage
    </h2>
    <p style="margin: 0 0 20px 0; font-size: 14px; color: #6b5b4c;">
        Ein Restaurant möchte ein Konto. Bitte prüfen und freischalten oder ablehnen.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td style="{{ $labelStyle }} {{ $rowStyle }}">Betrieb</td><td style="{{ $valueStyle }} {{ $rowStyle }}">{{ $profile->business_name }}</td></tr>
        <tr><td style="{{ $labelStyle }} {{ $rowStyle }}">Ansprechpartner</td><td style="{{ $valueStyle }} {{ $rowStyle }}">{{ $profile->contact_name }}</td></tr>
        <tr><td style="{{ $labelStyle }} {{ $rowStyle }}">E-Mail</td><td style="{{ $valueStyle }} {{ $rowStyle }}">{{ $profile->user?->email }}</td></tr>
        <tr><td style="{{ $labelStyle }} {{ $rowStyle }}">Telefon</td><td style="{{ $valueStyle }} {{ $rowStyle }}">{{ $profile->user?->phone }}</td></tr>
        <tr><td style="{{ $labelStyle }} {{ $rowStyle }}">Adresse</td><td style="{{ $valueStyle }} {{ $rowStyle }}">{{ $profile->billing_street }}, {{ $profile->billing_postal_code }} {{ $profile->billing_city }}</td></tr>
        @if ($profile->handelsregisternummer)
            <tr><td style="{{ $labelStyle }} {{ $rowStyle }}">Handelsregister</td><td style="{{ $valueStyle }} {{ $rowStyle }}">{{ $profile->handelsregisternummer }}</td></tr>
        @endif
        @if ($profile->ust_id_nr)
            <tr><td style="{{ $labelStyle }} {{ $rowStyle }}">USt-IdNr.</td><td style="{{ $valueStyle }} {{ $rowStyle }}">{{ $profile->ust_id_nr }}</td></tr>
        @endif
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 28px 0 8px 0;">
        <tr>
            <td style="background-color: #21341d; border-radius: 4px;">
                <a href="{{ $reviewUrl }}" style="display: inline-block; padding: 12px 24px; color: #faf5ec; font-size: 14px; font-weight: 600; text-decoration: none;">
                    Anfrage prüfen &rarr;
                </a>
            </td>
        </tr>
    </table>
    <p style="margin: 0; font-size: 12px; color: #6b5b4c;">
        Auf der Prüfseite kannst du freischalten oder ablehnen. Bei Ablehnung wird das Konto gelöscht.
    </p>
@endsection
