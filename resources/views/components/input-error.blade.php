@props(['messages', 'id' => null])

@if ($messages)
    <ul
        @if ($id) id="{{ $id }}" @endif
        {{ $attributes->merge(['class' => 'space-y-1 text-sm text-chapter-meat']) }}
    >
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
