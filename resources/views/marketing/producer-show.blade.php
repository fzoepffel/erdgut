@extends('layouts.marketing')

@section('content')
    @include('marketing._partials.producer-detail', [
        'producer' => $producer,
        'products' => $products,
        'indexUrl' => route('public.producers'),
        'indexLabel' => __('producer.detail.breadcrumb_producers'),
        'productRouteName' => 'public.products.show',
    ])
@endsection
