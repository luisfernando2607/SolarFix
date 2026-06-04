@extends('layouts.app')

@section('title', 'Nueva Orden')

@section('content')
    @include('orders._form', [
        'title' => 'Nueva Orden de Servicio',
        'subtitle' => 'Registrar ingreso de dispositivo al taller',
        'formAction' => route('orders.store'),
        'cancelRoute' => route('orders.index'),
        'submitText' => 'Crear Orden',
    ])
@endsection
