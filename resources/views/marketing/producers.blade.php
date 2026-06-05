@extends('layouts.marketing')

@section('content')
    {{-- Body shared with the isolated shop directory; defaults link into the
         marketing shell (public.producers.*, auth.*, public.contact). --}}
    @include('marketing._partials.producers-index')
@endsection
