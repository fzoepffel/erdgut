@extends('layouts.marketing')

@section('content')
    {{-- Body shared with the isolated shop catalogue; defaults link into the
         marketing shell (public.products.*). --}}
    @include('products._partials.public-index')
@endsection
