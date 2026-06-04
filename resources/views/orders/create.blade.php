@extends('layouts.app')

@section('title', 'Nueva Orden')

@section('content')
<div class="p-6 max-w-7xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('orders.index') }}" class="text-gray-400 hover:text-gray-600 p-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Nueva Orden de Servicio</h2>
                <p class="text-sm text-gray-500">Registrar ingreso de dispositivo al taller</p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('orders.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="grid grid-cols-2 gap-8">

            <div class="bg-white rounded-xl shadow-sm border">
                <div class="px-6 py-4 border-b bg-gray-50/50 rounded-t-xl flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <h3 class="text-base font-semibold text-gray-800">Información del Cliente</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="max-w-lg">
                        <label for="client_input" class="block text-sm font-medium text-gray-700 mb-1">Buscar cliente por nombre o cédula</label>
                        <div class="relative">
                            <input type="text" id="client_input" autocomplete="off"
                                placeholder="Escriba nombre o cédula para buscar..."
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('client_id') border-red-500 @enderror"
                                value="{{ old('client_id') ? ($clients->firstWhere('id', old('client_id'))->name ?? '') : '' }}">
                            <input type="hidden" name="client_id" id="client_id" value="{{ old('client_id') }}">
                        </div>
                        @error('client_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div id="clientInfo" class="{{ old('client_id') ? '' : 'hidden' }} p-4 bg-gray-50 rounded-lg border">
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div><span class="text-gray-500">Nombre:</span> <span class="font-medium" id="display_name"></span></div>
                            <div><span class="text-gray-500">Cédula:</span> <span class="font-medium" id="display_document"></span></div>
                            <div><span class="text-gray-500">Teléfono:</span> <span class="font-medium" id="display_phone"></span></div>
                            <div><span class="text-gray-500">Email:</span> <span class="font-medium" id="display_email"></span></div>
                        </div>
                    </div>

                    <div id="clientFields" class="{{ old('client_id') ? 'hidden' : '' }} grid grid-cols-2 gap-4">
                        <div>
                            <label for="client_name" class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                            <input type="text" id="client_name" name="client_name" value="{{ old('client_name') }}"
                                placeholder="Nombre completo"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('client_name') border-red-500 @enderror">
                            @error('client_name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="client_id_document" class="block text-sm font-medium text-gray-700 mb-1">Cédula</label>
                            <input type="text" id="client_id_document" name="client_id_document" value="{{ old('client_id_document') }}"
                                placeholder="Número de cédula"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label for="client_phone" class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                            <input type="text" id="client_phone" name="client_phone" value="{{ old('client_phone') }}"
                                placeholder="Número de teléfono"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label for="client_email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" id="client_email" name="client_email" value="{{ old('client_email') }}"
                                placeholder="correo@ejemplo.com"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                </div>

                <div class="border-t border-gray-200"></div>

                <div class="p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <h4 class="text-base font-semibold text-gray-800">Fotos del Dispositivo</h4>
                    </div>
                    @error('photos.*') <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror
                    <div id="dropzone"
                        class="border-2 border-dashed border-gray-300 rounded-xl text-center hover:border-blue-400 transition cursor-pointer"
                        style="min-height:180px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;padding:16px">
                        <div id="dropzoneEmpty" style="display:flex;flex-direction:column;align-items:center;gap:8px">
                            <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <div>
                                <p class="text-sm text-gray-500 font-medium">Arrastra y suelta tus fotos aquí</p>
                                <p class="text-xs text-gray-400 mt-1">o haz clic para seleccionar — JPEG, PNG, WebP</p>
                            </div>
                        </div>
                        <div id="photoPreviewPanel" class="hidden" style="width:100%">
                            <div id="photoPreviews" class="flex flex-wrap gap-2" style="justify-content:center"></div>
                        </div>
                        <input type="file" id="photoInput" name="photos[]" multiple accept="image/*" class="hidden" onchange="previewPhotos(event)">
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border">
                <div class="px-6 py-4 border-b bg-gray-50/50 rounded-t-xl flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    <h3 class="text-base font-semibold text-gray-800">Información del Dispositivo</h3>
                </div>
                <div class="p-6 space-y-5">
                    <div class="flex flex-wrap gap-4">
                        <div class="flex-1" style="min-width:120px">
                            <label for="brand_input" class="block text-sm font-medium text-gray-700 mb-1">Marca</label>
                            <div class="relative">
                                <input type="text" id="brand_input" name="brand_text" value="{{ old('brand_text') }}" autocomplete="off"
                                    placeholder="Buscar o escribir..."
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('brand_id') border-red-500 @enderror">
                                <input type="hidden" name="brand_id" id="brand_id" value="{{ old('brand_id') }}">
                            </div>
                            @error('brand_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div style="flex:2;min-width:140px">
                            <label for="model_input" class="block text-sm font-medium text-gray-700 mb-1">Modelo</label>
                            <div class="relative">
                                <input type="text" id="model_input" name="model_text" value="{{ old('model_text') }}" autocomplete="off"
                                    placeholder="Buscar o escribir..."
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('model_id') border-red-500 @enderror">
                                <input type="hidden" name="model_id" id="model_id" value="{{ old('model_id') }}">
                            </div>
                            @error('model_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex-1" style="min-width:120px">
                            <label for="device_type_input" class="block text-sm font-medium text-gray-700 mb-1">Tipo *</label>
                            <div class="relative">
                                <input type="text" id="device_type_input" autocomplete="off" required
                                    placeholder="Ej: Celular..."
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('device_type') border-red-500 @enderror"
                                    value="{{ old('device_type_label', old('device_type') ? ($deviceTypes[old('device_type')] ?? '') : '') }}">
                                <input type="hidden" name="device_type" id="device_type" value="{{ old('device_type') }}">
                            </div>
                            @error('device_type') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div style="flex:2;min-width:140px">
                            <label for="serial_imei" class="block text-sm font-medium text-gray-700 mb-1">Serial / IMEI</label>
                            <input type="text" id="serial_imei" name="serial_imei" value="{{ old('serial_imei') }}"
                                placeholder="Número de serie"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('serial_imei') border-red-500 @enderror">
                            @error('serial_imei') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-5">
                        <div>
                            <label for="entry_date" class="block text-sm font-medium text-gray-700 mb-1">Fecha Ingreso *</label>
                            <input type="date" id="entry_date" name="entry_date" value="{{ date('Y-m-d') }}" required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('entry_date') border-red-500 @enderror">
                            @error('entry_date') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="unlock_type" class="block text-sm font-medium text-gray-700 mb-1">Tipo Bloqueo *</label>
                            <select name="unlock_type" id="unlock_type" required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('unlock_type') border-red-500 @enderror">
                                @foreach ($unlockTypes as $key => $label)
                                    <option value="{{ $key }}" {{ old('unlock_type', 'unknown') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('unlock_type') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div id="unlockPinField" class="{{ old('unlock_type') === 'pattern' ? 'hidden' : '' }}">
                            <label for="unlock_value_input" class="block text-sm font-medium text-gray-700 mb-1">Valor PIN</label>
                            <input type="text" id="unlock_value_input"
                                placeholder="Ej: 1234"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                value="{{ old('unlock_type') !== 'pattern' ? old('unlock_value') : '' }}">
                        </div>
                    </div>

                    <div id="patternField" class="{{ old('unlock_type') === 'pattern' ? '' : 'hidden' }}">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Patrón de desbloqueo</label>
                        <div id="patternContainer" style="width:240px;height:240px;position:relative;margin:0 auto;touch-action:none;user-select:none">
                            <svg id="patternSvg" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none"></svg>
                            <div style="display:grid;grid-template-columns:repeat(3,1fr);grid-template-rows:repeat(3,1fr);width:100%;height:100%;place-items:center;padding:15px;box-sizing:border-box">
                                @for ($i = 0; $i < 9; $i++)
                                    <div class="pattern-dot" data-idx="{{ $i }}" style="width:22px;height:22px;border-radius:50%;background:#e5e7eb;border:3px solid #9ca3af;cursor:pointer;box-sizing:border-box;transition:background 0.15s,border-color 0.15s;z-index:2"></div>
                                @endfor
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 text-center mt-2">Dibuja el patrón — 4 puntos mínimo</p>
                    </div>
                    <input type="hidden" name="unlock_value" id="unlock_value" value="{{ old('unlock_value') }}">

                    <div class="grid grid-cols-2 gap-5">
                        <div>
                            <label for="physical_condition" class="block text-sm font-medium text-gray-700 mb-1">Condición Física</label>
                            <textarea id="physical_condition" name="physical_condition" rows="2"
                                placeholder="Estado físico del dispositivo..."
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('physical_condition') border-red-500 @enderror">{{ old('physical_condition') }}</textarea>
                            @error('physical_condition') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="declared_fault" class="block text-sm font-medium text-gray-700 mb-1">Falla Declarada *</label>
                            <textarea id="declared_fault" name="declared_fault" rows="2" required
                                placeholder="Falla reportada por el cliente..."
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('declared_fault') border-red-500 @enderror">{{ old('declared_fault') }}</textarea>
                            @error('declared_fault') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                        <textarea id="notes" name="notes" rows="1"
                            placeholder="Información adicional..."
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border" style="grid-column:1/-1">
                <div class="px-6 py-4 border-b bg-gray-50/50 rounded-t-xl flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <h3 class="text-base font-semibold text-gray-800">Costos</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-3 gap-5">
                        <div>
                            <label for="diagnosis_cost" class="block text-sm font-medium text-gray-700 mb-1">Costo Diagnóstico</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                                <input type="number" step="0.01" min="0" id="diagnosis_cost" name="diagnosis_cost" value="{{ old('diagnosis_cost', '0') }}"
                                    class="w-full pl-7 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none cost-input">
                            </div>
                        </div>

                        <div>
                            <label for="labor_cost" class="block text-sm font-medium text-gray-700 mb-1">Mano de Obra</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                                <input type="number" step="0.01" min="0" id="labor_cost" name="labor_cost" value="{{ old('labor_cost', '0') }}"
                                    class="w-full pl-7 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none cost-input">
                            </div>
                        </div>

                        <div>
                            <label for="parts_cost" class="block text-sm font-medium text-gray-700 mb-1">Repuestos</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                                <input type="number" step="0.01" min="0" id="parts_cost" name="parts_cost" value="{{ old('parts_cost', '0') }}"
                                    class="w-full pl-7 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none cost-input">
                            </div>
                        </div>

                        <div>
                            <label for="surcharge_percent" class="block text-sm font-medium text-gray-700 mb-1">Recargo (%)</label>
                            <input type="number" step="0.01" min="0" max="100" id="surcharge_percent" name="surcharge_percent" value="{{ old('surcharge_percent', '0') }}"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none cost-input">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Recargo calculado</label>
                            <p id="surcharge_amount_display" class="text-sm text-gray-500 pt-2.5">Recargo: <span class="font-semibold text-gray-700">$0.00</span></p>
                        </div>

                        <div>
                            <label for="total_amount" class="block text-sm font-medium text-gray-700 mb-1">Total</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                                <input type="number" step="0.01" min="0" id="total_amount" name="total_amount" value="0" readonly
                                    class="w-full pl-7 pr-4 py-2.5 border border-gray-200 bg-gray-50 rounded-lg text-gray-700 font-semibold outline-none cursor-not-allowed">
                            </div>
                        </div>

                        <div>
                            <label for="amount_paid" class="block text-sm font-medium text-gray-700 mb-1">Anticipo</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                                <input type="number" step="0.01" min="0" id="amount_paid" name="amount_paid" value="{{ old('amount_paid', '0') }}"
                                    class="w-full pl-7 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none cost-input">
                            </div>
                        </div>

                        <div>
                            <label for="balance_due" class="block text-sm font-medium text-gray-700 mb-1">Saldo Pendiente</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                                <input type="number" step="0.01" min="0" id="balance_due" name="balance_due" value="0" readonly
                                    class="w-full pl-7 pr-4 py-2.5 border border-gray-200 bg-gray-50 rounded-lg text-gray-700 font-semibold outline-none cursor-not-allowed">
                            </div>
                        </div>

                        <div>
                            <label for="warranty_days" class="block text-sm font-medium text-gray-700 mb-1">Garantía (días)</label>
                            <input type="number" min="0" id="warranty_days" name="warranty_days" value="{{ old('warranty_days', '0') }}"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border" style="grid-column:1/-1">
                <div class="px-6 py-4 border-b bg-gray-50/50 rounded-t-xl flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    <h3 class="text-base font-semibold text-gray-800">Accesorios Entregados</h3>
                </div>
                <div class="p-6">
                    @error('accessories') <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror
                    <div class="grid grid-cols-4 gap-3 max-h-48 overflow-y-auto p-2">
                        @foreach ($accessories as $accessory)
                            @php
                                $lower = strtolower($accessory->name);
                                $icon = match(true) {
                                    str_contains($lower, 'auricular') => '<svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
                                    str_contains($lower, 'cargador') && str_contains($lower, 'cable') => '<svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>',
                                    str_contains($lower, 'cargador') || str_contains($lower, 'bater') => '<svg class="w-4 h-4 text-yellow-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>',
                                    str_contains($lower, 'cable') => '<svg class="w-4 h-4 text-purple-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg>',
                                    str_contains($lower, 'funda') || str_contains($lower, 'caja') => '<svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>',
                                    str_contains($lower, 'control') => '<svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>',
                                    str_contains($lower, 'kit') => '<svg class="w-4 h-4 text-pink-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>',
                                    default => '<svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>',
                                };
                            @endphp
                            <label class="flex items-center gap-2 text-sm hover:bg-gray-50 p-1.5 rounded cursor-pointer">
                                <input type="checkbox" name="accessories[]" value="{{ $accessory->id }}"
                                    {{ in_array($accessory->id, old('accessories', [])) ? 'checked' : '' }}
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                {!! $icon !!}
                                {{ $accessory->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

        </div>

        <div class="mt-6 flex items-center gap-3 justify-end">
            <a href="{{ route('orders.index') }}" class="text-gray-600 hover:text-gray-800 px-5 py-2.5 text-sm font-medium">Cancelar</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition shadow-sm">
                Crear Orden
            </button>
        </div>
    </form>
</div>

<script>
    var brandsData = @json($brandsData);
    var modelsData = @json($modelsByBrand);
    var clientsData = @json($clientsData);
    var deviceTypesData = @json($deviceTypesData);

    var clientInput = document.getElementById('client_input');
    var clientHidden = document.getElementById('client_id');
    var clientInfo = document.getElementById('clientInfo');
    var clientFields = document.getElementById('clientFields');
    var brandInput = document.getElementById('brand_input');
    var brandHidden = document.getElementById('brand_id');
    var modelInput = document.getElementById('model_input');
    var modelHidden = document.getElementById('model_id');
    var deviceTypeInput = document.getElementById('device_type_input');
    var deviceTypeHidden = document.getElementById('device_type');

    function buildAutocomplete(input, hidden, getItems, onSelect) {
        var container = input.parentElement;
        var dropdown = document.createElement('div');
        dropdown.className = 'absolute z-50 w-full bg-white border border-gray-300 rounded-lg mt-1 shadow-lg max-h-48 overflow-y-auto hidden';
        container.style.position = 'relative';
        container.appendChild(dropdown);

        var selectedIdx = -1;

        function filter(q) {
            var items = getItems();
            if (!q) return [];
            q = q.toLowerCase().trim();
            return items.filter(function(item) {
                return item.name.toLowerCase().includes(q);
            }).slice(0, 15);
        }

        function render(results) {
            dropdown.innerHTML = '';
            selectedIdx = -1;
            if (!results.length) { dropdown.classList.add('hidden'); return; }
            dropdown.classList.remove('hidden');
            results.forEach(function(item, i) {
                var div = document.createElement('div');
                div.className = 'px-3 py-2 text-sm cursor-pointer hover:bg-blue-50 hover:text-blue-700';
                div.textContent = item.name;
                div.dataset.id = item.id;
                div.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    input.value = item.name;
                    hidden.value = item.id;
                    dropdown.classList.add('hidden');
                    if (onSelect) onSelect(item);
                });
                dropdown.appendChild(div);
            });
        }

        function highlightNext(dir) {
            var items = dropdown.querySelectorAll('div');
            if (!items.length) return;
            if (selectedIdx >= 0 && items[selectedIdx]) {
                items[selectedIdx].classList.remove('bg-blue-50', 'text-blue-700');
            }
            selectedIdx = Math.max(0, Math.min(selectedIdx + dir, items.length - 1));
            items[selectedIdx].classList.add('bg-blue-50', 'text-blue-700');
        }

        input.addEventListener('input', function() {
            hidden.value = '';
            render(filter(this.value));
            if (onSelect) onSelect(null);
        });

        input.addEventListener('focus', function() {
            if (this.value) render(filter(this.value));
        });

        input.addEventListener('blur', function() {
            setTimeout(function() { dropdown.classList.add('hidden'); }, 200);
        });

        input.addEventListener('keydown', function(e) {
            var items = dropdown.querySelectorAll('div');
            if (e.key === 'ArrowDown') { e.preventDefault(); highlightNext(1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); highlightNext(-1); }
            else if (e.key === 'Enter' && items.length && selectedIdx >= 0) {
                e.preventDefault();
                items[selectedIdx].click();
            }
            else if (e.key === 'Escape') { dropdown.classList.add('hidden'); }
        });
    }

    function selectClient(client) {
        if (client) {
            clientHidden.value = client.id;
            clientInput.value = client.name;
            document.getElementById('display_name').textContent = client.name;
            document.getElementById('display_document').textContent = client.id_document || '—';
            document.getElementById('display_phone').textContent = client.phone || '—';
            document.getElementById('display_email').textContent = client.email || '—';
            clientInfo.classList.remove('hidden');
            clientFields.classList.add('hidden');
            document.getElementById('client_name').removeAttribute('required');
        } else {
            clientHidden.value = '';
            clientInfo.classList.add('hidden');
            clientFields.classList.remove('hidden');
            document.getElementById('client_name').setAttribute('required', '');
        }
    }

    buildAutocomplete(clientInput, clientHidden, function() {
        var q = (clientInput.value || '').toLowerCase().trim();
        if (!q) return [];
        return clientsData.filter(function(c) {
            return c.name.toLowerCase().includes(q) || (c.id_document && c.id_document.includes(q));
        }).slice(0, 15);
    }, function(item) {
        selectClient(item);
    });

    clientInput.addEventListener('input', function() {
        if (!this.value) selectClient(null);
    });

    buildAutocomplete(deviceTypeInput, deviceTypeHidden, function() {
        return deviceTypesData;
    });

    buildAutocomplete(brandInput, brandHidden, function() {
        return brandsData;
    }, function(item) {
        if (item) {
            modelInput.value = '';
            modelHidden.value = '';
            modelInput.focus();
        }
    });

    buildAutocomplete(modelInput, modelHidden, function() {
        var bid = brandHidden.value;
        if (!bid || !modelsData[bid]) return [];
        return modelsData[bid];
    }, function(item) {
        if (item && item.device_type) {
            var dt = deviceTypesData.find(function(d) { return d.id === item.device_type; });
            if (dt) {
                deviceTypeInput.value = dt.name;
                deviceTypeHidden.value = dt.id;
            }
        }
    });

    var photoFiles = [];

    window.previewPhotos = function(event) {
        var files = Array.from(event.target.files);

        files.forEach(function(file) {
            photoFiles.push(file);
        });

        updatePreviewPanel();
        event.target.value = '';
    };

    function updatePreviewPanel() {
        var panel = document.getElementById('photoPreviewPanel');
        var container = document.getElementById('photoPreviews');
        var empty = document.getElementById('dropzoneEmpty');

        if (!photoFiles.length) {
            empty.style.display = 'flex';
            panel.classList.add('hidden');
            document.getElementById('dropzone').style.padding = '16px';
            return;
        }

        empty.style.display = 'none';
        panel.classList.remove('hidden');
        document.getElementById('dropzone').style.padding = '12px';

        container.innerHTML = '';

        photoFiles.forEach(function(file, index) {
            (function(idx) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var wrapper = document.createElement('div');
                    wrapper.className = 'relative group rounded-lg overflow-hidden border bg-gray-100 shrink-0';
                    wrapper.style.width = '100px';
                    wrapper.style.height = '100px';
                    wrapper.innerHTML =
                        '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover">' +
                        '<div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">' +
                            '<button type="button" onclick="event.stopPropagation();removePhoto(' + idx + ')" class="p-1.5 bg-red-500 hover:bg-red-600 text-white rounded-full transition shadow-md" title="Eliminar">' +
                                '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>' +
                            '</button>' +
                        '</div>';
                    container.appendChild(wrapper);
                };
                reader.readAsDataURL(file);
            })(index);
        });
    }

    window.removePhoto = function(index) {
        photoFiles.splice(index, 1);

        var dt = new DataTransfer();
        photoFiles.forEach(function(f) { dt.items.add(f); });
        document.getElementById('photoInput').files = dt.files;

        updatePreviewPanel();
    };

    var dropzone = document.getElementById('dropzone');

    dropzone.addEventListener('click', function() {
        document.getElementById('photoInput').click();
    });

    dropzone.addEventListener('dragenter', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.remove('border-gray-300', 'border-dashed');
        dropzone.classList.add('border-blue-500', 'border-solid', 'bg-blue-50');
    });

    dropzone.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
    });

    dropzone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.remove('border-blue-500', 'border-solid', 'bg-blue-50');
        dropzone.classList.add('border-gray-300', 'border-dashed');
    });

    dropzone.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.remove('border-blue-500', 'border-solid', 'bg-blue-50');
        dropzone.classList.add('border-gray-300', 'border-dashed');

        var files = Array.from(e.dataTransfer.files);
        files.forEach(function(file) {
            if (file.type.startsWith('image/')) {
                photoFiles.push(file);
            }
        });

        var dt = new DataTransfer();
        photoFiles.forEach(function(f) { dt.items.add(f); });
        document.getElementById('photoInput').files = dt.files;

        updatePreviewPanel();
    });

    var unlockType = document.getElementById('unlock_type');
    var unlockPinField = document.getElementById('unlockPinField');
    var patternField = document.getElementById('patternField');
    var unlockValueHidden = document.getElementById('unlock_value');
    var unlockValueInput = document.getElementById('unlock_value_input');

    function toggleUnlockFields() {
        if (unlockType.value === 'pattern') {
            unlockPinField.classList.add('hidden');
            patternField.classList.remove('hidden');
        } else {
            unlockPinField.classList.remove('hidden');
            patternField.classList.add('hidden');
            if (unlockValueInput) {
                unlockValueInput.value = unlockValueHidden.value;
            }
        }
    }

    unlockType.addEventListener('change', function() {
        if (this.value === 'pattern') {
            unlockValueHidden.value = '';
            clearPattern();
        } else {
            patternSequence = [];
            drawPattern();
        }
        toggleUnlockFields();
    });

    if (unlockValueInput) {
        unlockValueInput.addEventListener('input', function() {
            unlockValueHidden.value = this.value;
        });
        if (unlockValueHidden.value) {
            unlockValueInput.value = unlockValueHidden.value;
        }
    }

    var patternSequence = [];
    var isDrawing = false;
    var patternContainer = document.getElementById('patternContainer');
    var patternSvg = document.getElementById('patternSvg');
    var dots = document.querySelectorAll('.pattern-dot');
    function getDotCenter(dot) {
        var rect = dot.getBoundingClientRect();
        var containerRect = patternContainer.getBoundingClientRect();
        return {
            x: rect.left - containerRect.left + rect.width / 2,
            y: rect.top - containerRect.top + rect.height / 2
        };
    }

    function getDotPositions() {
        var positions = [];
        dots.forEach(function(d) { positions.push(getDotCenter(d)); });
        return positions;
    }

    function addDotToPattern(idx) {
        if (patternSequence.includes(idx)) return;
        patternSequence.push(idx);
        dots[idx].style.background = '#3b82f6';
        dots[idx].style.borderColor = '#2563eb';
        drawPattern();
        unlockValueHidden.value = patternSequence.join('-');
    }

    function drawPattern() {
        while (patternSvg.firstChild) patternSvg.removeChild(patternSvg.firstChild);
        if (patternSequence.length < 2) return;
        var positions = getDotPositions();
        var svgNs = 'http://www.w3.org/2000/svg';
        for (var i = 0; i < patternSequence.length - 1; i++) {
            var from = positions[patternSequence[i]];
            var to = positions[patternSequence[i + 1]];
            var line = document.createElementNS(svgNs, 'line');
            line.setAttribute('x1', from.x);
            line.setAttribute('y1', from.y);
            line.setAttribute('x2', to.x);
            line.setAttribute('y2', to.y);
            line.setAttribute('stroke', '#3b82f6');
            line.setAttribute('stroke-width', '4');
            line.setAttribute('stroke-linecap', 'round');
            patternSvg.appendChild(line);
        }
    }

    function clearPattern() {
        patternSequence = [];
        dots.forEach(function(d) {
            d.style.background = '#e5e7eb';
            d.style.borderColor = '#9ca3af';
        });
        while (patternSvg.firstChild) patternSvg.removeChild(patternSvg.firstChild);
        unlockValueHidden.value = '';
    }

    function getDotIndexFromPoint(clientX, clientY) {
        var containerRect = patternContainer.getBoundingClientRect();
        var relX = clientX - containerRect.left;
        var relY = clientY - containerRect.top;
        var positions = getDotPositions();
        for (var i = 0; i < dots.length; i++) {
            var pos = positions[i];
            var dist = Math.sqrt((relX - pos.x) ** 2 + (relY - pos.y) ** 2);
            if (dist < 25) return i;
        }
        return -1;
    }

    function onPatternStart(e) {
        e.preventDefault();
        isDrawing = true;
        var pt = e.type.startsWith('touch') ? e.touches[0] : e;
        var idx = getDotIndexFromPoint(pt.clientX, pt.clientY);
        if (idx >= 0) addDotToPattern(idx);
    }

    function onPatternMove(e) {
        e.preventDefault();
        if (!isDrawing) return;
        var pt = e.type.startsWith('touch') ? e.touches[0] : e;
        var idx = getDotIndexFromPoint(pt.clientX, pt.clientY);
        if (idx >= 0 && !patternSequence.includes(idx)) {
            addDotToPattern(idx);
        }
    }

    function onPatternEnd(e) {
        e.preventDefault();
        isDrawing = false;
    }

    function initPattern() {
        patternContainer.addEventListener('mousedown', onPatternStart);
        document.addEventListener('mousemove', onPatternMove);
        document.addEventListener('mouseup', onPatternEnd);

        patternContainer.addEventListener('touchstart', onPatternStart, { passive: false });
        document.addEventListener('touchmove', onPatternMove, { passive: false });
        document.addEventListener('touchend', onPatternEnd, { passive: false });

        if (unlockValueHidden.value) {
            var saved = unlockValueHidden.value.split('-').map(Number);
            saved.forEach(function(idx) {
                if (!isNaN(idx) && idx >= 0 && idx < 9) {
                    patternSequence.push(idx);
                    dots[idx].style.background = '#3b82f6';
                    dots[idx].style.borderColor = '#2563eb';
                }
            });
            drawPattern();
        }
    }

    if (patternContainer) {
        setTimeout(initPattern, 100);
    }

    function calcCosts() {
        var diag = parseFloat(document.getElementById('diagnosis_cost').value) || 0;
        var labor = parseFloat(document.getElementById('labor_cost').value) || 0;
        var parts = parseFloat(document.getElementById('parts_cost').value) || 0;
        var surchPct = parseFloat(document.getElementById('surcharge_percent').value) || 0;
        var paid = parseFloat(document.getElementById('amount_paid').value) || 0;

        var sub = diag + labor + parts;
        var surchAmt = sub * (surchPct / 100);
        var total = sub + surchAmt;
        var balance = total - paid;

        document.getElementById('surcharge_amount_display').innerHTML = 'Recargo: <span class="font-semibold text-gray-700">$' + surchAmt.toFixed(2) + '</span>';
        document.getElementById('total_amount').value = total.toFixed(2);
        document.getElementById('balance_due').value = Math.max(0, balance).toFixed(2);

        var balEl = document.getElementById('balance_due');
        if (balance > 0) {
            balEl.classList.remove('text-green-700');
            balEl.classList.add('text-red-600');
        } else {
            balEl.classList.remove('text-red-600');
            balEl.classList.add('text-green-700');
        }
    }

    document.querySelectorAll('.cost-input').forEach(function(el) {
        el.addEventListener('input', calcCosts);
    });
    calcCosts();
</script>
@endsection
