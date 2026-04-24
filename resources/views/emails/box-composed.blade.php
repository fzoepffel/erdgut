@extends('emails.layout')

@section('content')
<p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #e46f25;">{{ __('emails.box_composed.eyebrow') }}</p>
<h2 style="margin: 0 0 20px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 600; color: #2c241c;">{{ __('emails.box_composed.heading') }}</h2>
<p style="margin: 0 0 20px 0;">{{ __('emails.greeting', ['name' => $box->user->name]) }}</p>
<p style="margin: 0 0 20px 0;">
    {{ __('emails.box_composed.intro', [
        'week' => \Illuminate\Support\Carbon::parse($box->week_start_date)->translatedFormat('j. F'),
    ]) }}
</p>
@php($items = $box->items()->with('product')->get())
@if($items->isNotEmpty())
<ul style="margin: 0 0 24px 0; padding-left: 20px;">
    @foreach($items as $item)
        <li style="margin-bottom: 6px;">
            {{ $item->quantity }} × {{ __($item->product->name ?? '—') }}
        </li>
    @endforeach
</ul>
@endif
<p style="margin: 0 0 20px 0;">{{ __('emails.box_composed.deadline_copy') }}</p>
<p style="margin: 0 0 32px 0;">
    <a href="{{ url('/app/dashboard') }}" style="display: inline-block; padding: 12px 24px; background-color: #2c241c; color: #f1e7d6; text-decoration: none; font-weight: 600; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">{{ __('emails.box_composed.cta') }}</a>
</p>
<p style="margin: 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 14px; color: #6b5b4c;">{{ __('emails.box_ref', ['id' => $box->id]) }}</p>
@endsection
