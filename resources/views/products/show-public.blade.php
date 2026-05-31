@extends('layouts.marketing')

@section('content')
    @include('products._partials.public-detail', [
        'product' => $product,
        'indexUrl' => route('public.products.index'),
        'indexLabel' => __('Products'),
        'productRouteName' => 'public.products.show',
        'producerRouteName' => 'public.producers.show',
    ])
@endsection
