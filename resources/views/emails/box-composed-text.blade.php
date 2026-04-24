{{ __('emails.greeting', ['name' => $box->user->name]) }}

{{ __('emails.box_composed.heading') }}

{{ __('emails.box_composed.intro', [
    'week' => \Illuminate\Support\Carbon::parse($box->week_start_date)->translatedFormat('j. F'),
]) }}
@php($items = $box->items()->with('product')->get())
@foreach($items as $item)
- {{ $item->quantity }} × {{ __($item->product->name ?? '—') }}
@endforeach

{{ __('emails.box_composed.deadline_copy') }}

{{ url('/app/dashboard') }}

— {{ __('emails.layout.signature') }}

{{ __('emails.box_ref', ['id' => $box->id]) }}
