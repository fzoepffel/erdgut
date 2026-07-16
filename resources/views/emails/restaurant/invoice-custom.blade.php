@extends('emails.layout')

@section('content')
    {{-- Free text from the admin's mail editor; line breaks preserved. --}}
    <p style="margin: 0 0 16px 0; font-size: 15px; color: #2c241c; white-space: pre-line;">{{ $body }}</p>
@endsection
