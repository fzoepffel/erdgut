@extends('layouts.marketing')

@section('content')
    @include('products._partials.detail', [
        'product' => $product,
        'listRouteName' => 'public.products.index',
        'detailRouteName' => 'public.products.show',
    ])
@endsection
