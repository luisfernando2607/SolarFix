@extends('layouts.app')

@section('title', 'Editar Orden #' . $order->order_number)

@section('content')
    @include('orders._form', [
        'order' => $order,
        'title' => 'Editar Orden #' . $order->order_number,
        'subtitle' => 'Modificar información de la orden de servicio',
        'formAction' => route('orders.update', $order),
        'cancelRoute' => route('orders.index'),
        'submitText' => 'Guardar Cambios',
    ])
@endsection
