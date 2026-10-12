@extends('layouts.app')

@section('title', 'Configuración')

@php
    $input = 'w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none';
@endphp

@section('content')
<div class="p-6 max-w-3xl mx-auto">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Configuración</h2>
        <p class="text-gray-500">Datos del negocio, facturación y alertas.</p>
    </div>

    <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-1">Negocio</h3>
            <p class="text-xs text-gray-500 mb-4">Aparece en los mensajes de WhatsApp.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach (['company_name' => 'Nombre *', 'company_phone' => 'Teléfono', 'company_email' => 'Email', 'company_address' => 'Dirección'] as $key => $label)
                    <div>
                        <label for="{{ $key }}" class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</label>
                        <input type="{{ $key === 'company_email' ? 'email' : 'text' }}" id="{{ $key }}" name="{{ $key }}"
                            value="{{ old($key, $settings[$key]) }}" {{ $key === 'company_name' ? 'required' : '' }}
                            class="{{ $input }} @error($key) border-red-500 @enderror">
                        @error($key) <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-4">Facturación y órdenes</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="iva_percent" class="block text-sm font-medium text-gray-700 mb-1">IVA (%) *</label>
                    <input type="number" step="0.01" min="0" max="100" id="iva_percent" name="iva_percent"
                        value="{{ old('iva_percent', $settings['iva_percent']) }}" required
                        class="{{ $input }} @error('iva_percent') border-red-500 @enderror">
                    @error('iva_percent') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="default_warranty_days" class="block text-sm font-medium text-gray-700 mb-1">Garantía por defecto (días) *</label>
                    <input type="number" min="0" id="default_warranty_days" name="default_warranty_days"
                        value="{{ old('default_warranty_days', $settings['default_warranty_days']) }}" required
                        class="{{ $input }} @error('default_warranty_days') border-red-500 @enderror">
                    @error('default_warranty_days') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <label class="flex items-start gap-2 mt-4">
                <input type="hidden" name="prices_include_iva" value="0">
                <input type="checkbox" name="prices_include_iva" value="1" {{ old('prices_include_iva', $settings['prices_include_iva']) ? 'checked' : '' }}
                    class="w-4 h-4 mt-0.5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <span class="text-sm text-gray-700">
                    Los precios de las órdenes incluyen IVA
                    <span class="block text-xs text-gray-500">Al facturar una orden se desglosa la base y el IVA, y el total coincide con el de la orden. Si se desmarca, el IVA se suma al total de la orden.</span>
                </span>
            </label>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-1">Alertas del dashboard</h3>
            <p class="text-xs text-gray-500 mb-4">Cuándo marcar una orden como demorada.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="stale_order_days" class="block text-sm font-medium text-gray-700 mb-1">Días sin movimiento *</label>
                    <input type="number" min="1" id="stale_order_days" name="stale_order_days"
                        value="{{ old('stale_order_days', $settings['stale_order_days']) }}" required
                        class="{{ $input }} @error('stale_order_days') border-red-500 @enderror">
                    @error('stale_order_days') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="approval_alert_hours" class="block text-sm font-medium text-gray-700 mb-1">Horas esperando aprobación *</label>
                    <input type="number" min="1" id="approval_alert_hours" name="approval_alert_hours"
                        value="{{ old('approval_alert_hours', $settings['approval_alert_hours']) }}" required
                        class="{{ $input }} @error('approval_alert_hours') border-red-500 @enderror">
                    @error('approval_alert_hours') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">
                Guardar configuración
            </button>
        </div>
    </form>
</div>
@endsection
