@extends('layouts.app')

@section('title', 'Nueva Orden')

@section('content')
<div class="p-6 max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('orders.index') }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Volver a órdenes
        </a>
        <h2 class="text-2xl font-bold text-gray-800 mt-2">Nueva Orden de Servicio</h2>
        <p class="text-gray-500">Registrar ingreso de dispositivo</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <form method="POST" action="{{ route('orders.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <div class="md:col-span-2">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Información del Cliente</h3>
                </div>

                <div>
                    <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">Cliente *</label>
                    <select name="client_id" id="client_id" required
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('client_id') border-red-500 @enderror">
                        <option value="">Seleccionar cliente...</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>
                                {{ $client->name }} {{ $client->id_document ? '- ' . $client->id_document : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('client_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Información del Dispositivo</h3>
                </div>

                <div>
                    <label for="device_type" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Dispositivo *</label>
                    <select name="device_type" id="device_type" required
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('device_type') border-red-500 @enderror">
                        <option value="">Seleccionar tipo...</option>
                        @foreach ($deviceTypes as $key => $label)
                            <option value="{{ $key }}" {{ old('device_type') == $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('device_type') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="brand_id" class="block text-sm font-medium text-gray-700 mb-1">Marca</label>
                    <select name="brand_id" id="brand_id"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('brand_id') border-red-500 @enderror">
                        <option value="">Seleccionar marca...</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}" data-types="{{ json_encode($brand->device_types ?? []) }}" {{ old('brand_id') == $brand->id ? 'selected' : '' }}>
                                {{ $brand->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('brand_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="model_id" class="block text-sm font-medium text-gray-700 mb-1">Modelo</label>
                    <select name="model_id" id="model_id"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('model_id') border-red-500 @enderror">
                        <option value="">Seleccionar modelo...</option>
                    </select>
                    @error('model_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <div class="border-t pt-4">
                        <p class="text-xs text-gray-500 mb-2">O escribe marca y modelo manualmente (si no está en la lista):</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="brand_text" class="block text-sm font-medium text-gray-700 mb-1">Marca (texto libre)</label>
                                <input type="text" id="brand_text" name="brand_text" value="{{ old('brand_text') }}"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label for="model_text" class="block text-sm font-medium text-gray-700 mb-1">Modelo (texto libre)</label>
                                <input type="text" id="model_text" name="model_text" value="{{ old('model_text') }}"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="serial_imei" class="block text-sm font-medium text-gray-700 mb-1">Serial / IMEI</label>
                    <input type="text" id="serial_imei" name="serial_imei" value="{{ old('serial_imei') }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('serial_imei') border-red-500 @enderror">
                    @error('serial_imei') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="entry_date" class="block text-sm font-medium text-gray-700 mb-1">Fecha de Ingreso *</label>
                    <input type="date" id="entry_date" name="entry_date" value="{{ old('entry_date', date('Y-m-d')) }}" required
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('entry_date') border-red-500 @enderror">
                    @error('entry_date') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="unlock_type" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Bloqueo *</label>
                    <select name="unlock_type" id="unlock_type" required
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('unlock_type') border-red-500 @enderror">
                        @foreach ($unlockTypes as $key => $label)
                            <option value="{{ $key }}" {{ old('unlock_type', 'unknown') == $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('unlock_type') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="unlock_value" class="block text-sm font-medium text-gray-700 mb-1">Valor de Bloqueo (PIN/Patrón)</label>
                    <input type="text" id="unlock_value" name="unlock_value" value="{{ old('unlock_value') }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>

                <div>
                    <label for="physical_condition" class="block text-sm font-medium text-gray-700 mb-1">Condición Física</label>
                    <textarea id="physical_condition" name="physical_condition" rows="3"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('physical_condition') border-red-500 @enderror">{{ old('physical_condition') }}</textarea>
                    @error('physical_condition') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="declared_fault" class="block text-sm font-medium text-gray-700 mb-1">Falla Declarada *</label>
                    <textarea id="declared_fault" name="declared_fault" rows="3" required
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('declared_fault') border-red-500 @enderror">{{ old('declared_fault') }}</textarea>
                    @error('declared_fault') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                    <textarea id="notes" name="notes" rows="3"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">{{ old('notes') }}</textarea>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Fotos del Dispositivo</label>
                    @error('photos.*') <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-blue-400 transition cursor-pointer" id="dropzone" onclick="document.getElementById('photoInput').click()">
                        <svg class="w-8 h-8 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <p class="text-sm text-gray-500">Haz clic para seleccionar fotos</p>
                        <p class="text-xs text-gray-400 mt-1">JPEG, PNG, WebP. Máx 10MB c/u</p>
                        <input type="file" id="photoInput" name="photos[]" multiple accept="image/*" class="hidden" onchange="previewPhotos(event)">
                    </div>
                    <div id="photoPreviews" class="grid grid-cols-3 md:grid-cols-4 gap-3 mt-3"></div>
                </div>

                <script>
                    function previewPhotos(event) {
                        var container = document.getElementById('photoPreviews');
                        container.innerHTML = '';
                        Array.from(event.target.files).forEach(function(file) {
                            var reader = new FileReader();
                            reader.onload = function(e) {
                                var div = document.createElement('div');
                                div.className = 'relative group aspect-square rounded-lg overflow-hidden border bg-gray-100';
                                div.innerHTML = '<img src="' + e.target.result + '" class="w-full h-full object-cover">' +
                                    '<button type="button" onclick="this.parentElement.remove()" class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition">&times;</button>';
                                container.appendChild(div);
                            };
                            reader.readAsDataURL(file);
                        });
                    }
                </script>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Accesorios Entregados</label>
                    @error('accessories') <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror
                    <div class="grid grid-cols-2 gap-2 max-h-48 overflow-y-auto p-2 border rounded-lg">
                        @foreach ($accessories as $accessory)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="accessories[]" value="{{ $accessory->id }}"
                                    {{ in_array($accessory->id, old('accessories', [])) ? 'checked' : '' }}
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                {{ $accessory->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3 pt-4 border-t">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">
                    Crear Orden
                </button>
                <a href="{{ route('orders.index') }}" class="text-gray-600 hover:text-gray-800 px-4 py-2.5 text-sm font-medium transition">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    var modelsByBrand = @json($modelsByBrand);

    var brandSelect = document.getElementById('brand_id');
    var modelSelect = document.getElementById('model_id');
    var deviceTypeSelect = document.getElementById('device_type');

    function filterBrands() {
        var type = deviceTypeSelect.value;
        Array.from(brandSelect.options).forEach(function(opt) {
            if (opt.value === '') return;
            var types = JSON.parse(opt.getAttribute('data-types') || '[]');
            opt.style.display = type && types.length && !types.includes(type) ? 'none' : '';
        });
        if (brandSelect.selectedIndex > 0) {
            var selected = brandSelect.options[brandSelect.selectedIndex];
            if (selected.style.display === 'none') brandSelect.value = '';
        }
        filterModels();
    }

    function filterModels() {
        var brandId = brandSelect.value;
        var type = deviceTypeSelect.value;
        modelSelect.innerHTML = '<option value="">Seleccionar modelo...</option>';
        if (brandId && modelsByBrand[brandId]) {
            modelsByBrand[brandId].forEach(function(m) {
                if (!type || m.device_type === type || !m.device_type) {
                    var opt = document.createElement('option');
                    opt.value = m.id;
                    opt.textContent = m.name;
                    modelSelect.appendChild(opt);
                }
            });
        }
    }

    deviceTypeSelect.addEventListener('change', filterBrands);
    brandSelect.addEventListener('change', filterModels);
    filterBrands();
</script>
@endsection
