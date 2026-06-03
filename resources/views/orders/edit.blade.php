@extends('layouts.app')

@section('title', 'Editar Orden #' . $order->order_number)

@section('content')
<div class="p-6 max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('orders.show', $order) }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Volver a orden
        </a>
        <h2 class="text-2xl font-bold text-gray-800 mt-2">Editar Orden #{{ $order->order_number }}</h2>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <form method="POST" action="{{ route('orders.update', $order) }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <div class="md:col-span-2">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Información del Cliente</h3>
                </div>

                <div>
                    <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">Cliente *</label>
                    <select name="client_id" id="client_id" required
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('client_id') border-red-500 @enderror">
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" {{ old('client_id', $order->client_id) == $client->id ? 'selected' : '' }}>
                                {{ $client->name }} {{ $client->id_document ? '- ' . $client->id_document : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('client_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div></div>

                <div class="md:col-span-2">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Información del Dispositivo</h3>
                </div>

                <div>
                    <label for="device_type" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Dispositivo *</label>
                    <select name="device_type" id="device_type" required
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('device_type') border-red-500 @enderror">
                        @foreach ($deviceTypes as $key => $label)
                            <option value="{{ $key }}" {{ old('device_type', $order->device_type) == $key ? 'selected' : '' }}>{{ $label }}</option>
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
                            <option value="{{ $brand->id }}" data-types="{{ json_encode($brand->device_types ?? []) }}"
                                {{ old('brand_id', $order->brand_id) == $brand->id ? 'selected' : '' }}>
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
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="brand_text" class="block text-sm font-medium text-gray-700 mb-1">Marca (texto libre)</label>
                            <input type="text" id="brand_text" name="brand_text" value="{{ old('brand_text', $order->brand_text) }}"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label for="model_text" class="block text-sm font-medium text-gray-700 mb-1">Modelo (texto libre)</label>
                            <input type="text" id="model_text" name="model_text" value="{{ old('model_text', $order->model_text) }}"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                </div>

                <div>
                    <label for="serial_imei" class="block text-sm font-medium text-gray-700 mb-1">Serial / IMEI</label>
                    <input type="text" id="serial_imei" name="serial_imei" value="{{ old('serial_imei', $order->serial_imei) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>

                <div>
                    <label for="entry_date" class="block text-sm font-medium text-gray-700 mb-1">Fecha de Ingreso *</label>
                    <input type="date" id="entry_date" name="entry_date" value="{{ old('entry_date', $order->entry_date?->format('Y-m-d')) }}" required
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <select name="status" id="status"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        @foreach ($statuses as $key => $label)
                            <option value="{{ $key }}" {{ old('status', $order->status) == $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="estimated_delivery" class="block text-sm font-medium text-gray-700 mb-1">Entrega Estimada</label>
                    <input type="date" id="estimated_delivery" name="estimated_delivery" value="{{ old('estimated_delivery', $order->estimated_delivery?->format('Y-m-d')) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>

                <div>
                    <label for="delivery_date" class="block text-sm font-medium text-gray-700 mb-1">Fecha de Entrega</label>
                    <input type="date" id="delivery_date" name="delivery_date" value="{{ old('delivery_date', $order->delivery_date?->format('Y-m-d')) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>

                <div></div>

                <div>
                    <label for="unlock_type" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Bloqueo *</label>
                    <select name="unlock_type" id="unlock_type" required
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        @foreach ($unlockTypes as $key => $label)
                            <option value="{{ $key }}" {{ old('unlock_type', $order->unlock_type) == $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="unlock_value" class="block text-sm font-medium text-gray-700 mb-1">Valor de Bloqueo</label>
                    <input type="text" id="unlock_value" name="unlock_value" value="{{ old('unlock_value', $order->unlock_value) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>

                <div>
                    <label for="physical_condition" class="block text-sm font-medium text-gray-700 mb-1">Condición Física</label>
                    <textarea id="physical_condition" name="physical_condition" rows="3"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">{{ old('physical_condition', $order->physical_condition) }}</textarea>
                </div>

                <div>
                    <label for="declared_fault" class="block text-sm font-medium text-gray-700 mb-1">Falla Declarada *</label>
                    <textarea id="declared_fault" name="declared_fault" rows="3" required
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">{{ old('declared_fault', $order->declared_fault) }}</textarea>
                </div>

                <div>
                    <label for="diagnosis" class="block text-sm font-medium text-gray-700 mb-1">Diagnóstico</label>
                    <textarea id="diagnosis" name="diagnosis" rows="3"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">{{ old('diagnosis', $order->diagnosis) }}</textarea>
                </div>

                <div>
                    <label for="work_done" class="block text-sm font-medium text-gray-700 mb-1">Trabajo Realizado</label>
                    <textarea id="work_done" name="work_done" rows="3"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">{{ old('work_done', $order->work_done) }}</textarea>
                </div>

                <div class="md:col-span-2">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Costos</h3>
                </div>

                <div>
                    <label for="diagnosis_cost" class="block text-sm font-medium text-gray-700 mb-1">Costo Diagnóstico</label>
                    <input type="number" step="0.01" min="0" id="diagnosis_cost" name="diagnosis_cost" value="{{ old('diagnosis_cost', $order->diagnosis_cost) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>

                <div>
                    <label for="labor_cost" class="block text-sm font-medium text-gray-700 mb-1">Mano de Obra</label>
                    <input type="number" step="0.01" min="0" id="labor_cost" name="labor_cost" value="{{ old('labor_cost', $order->labor_cost) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>

                <div>
                    <label for="parts_cost" class="block text-sm font-medium text-gray-700 mb-1">Repuestos</label>
                    <input type="number" step="0.01" min="0" id="parts_cost" name="parts_cost" value="{{ old('parts_cost', $order->parts_cost) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>

                <div>
                    <label for="surcharge_percent" class="block text-sm font-medium text-gray-700 mb-1">Recargo (%)</label>
                    <input type="number" step="0.01" min="0" max="100" id="surcharge_percent" name="surcharge_percent" value="{{ old('surcharge_percent', $order->surcharge_percent) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>

                <div>
                    <label for="total_amount" class="block text-sm font-medium text-gray-700 mb-1">Total</label>
                    <input type="number" step="0.01" min="0" id="total_amount" name="total_amount" value="{{ old('total_amount', $order->total_amount) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>

                <div>
                    <label for="amount_paid" class="block text-sm font-medium text-gray-700 mb-1">Monto Pagado</label>
                    <input type="number" step="0.01" min="0" id="amount_paid" name="amount_paid" value="{{ old('amount_paid', $order->amount_paid) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>

                <div>
                    <label for="balance_due" class="block text-sm font-medium text-gray-700 mb-1">Saldo Pendiente</label>
                    <input type="number" step="0.01" min="0" id="balance_due" name="balance_due" value="{{ old('balance_due', $order->balance_due) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>

                <div>
                    <label for="warranty_days" class="block text-sm font-medium text-gray-700 mb-1">Garantía (días)</label>
                    <input type="number" min="0" id="warranty_days" name="warranty_days" value="{{ old('warranty_days', $order->warranty_days) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>

                <div class="md:col-span-2">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                    <textarea id="notes" name="notes" rows="3"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">{{ old('notes', $order->notes) }}</textarea>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Accesorios Entregados</label>
                    @error('accessories') <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 max-h-48 overflow-y-auto p-2 border rounded-lg">
                        @php $selectedAccs = old('accessories', $order->accessories->pluck('id')->toArray()); @endphp
                        @foreach ($accessories as $accessory)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="accessories[]" value="{{ $accessory->id }}"
                                    {{ in_array($accessory->id, $selectedAccs) ? 'checked' : '' }}
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                {{ $accessory->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3 pt-4 border-t">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">
                    Guardar Cambios
                </button>
                <a href="{{ route('orders.show', $order) }}" class="text-gray-600 hover:text-gray-800 px-4 py-2.5 text-sm font-medium transition">
                    Cancelar
                </a>
            </div>
        </form>

        <div id="photos" class="mt-8 pt-6 border-t">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Fotos del Dispositivo</h3>

            @if ($order->photos->count())
                <div class="grid grid-cols-3 md:grid-cols-4 gap-3 mb-4">
                    @foreach ($order->photos as $photo)
                        <div class="relative group aspect-square rounded-lg overflow-hidden border bg-gray-100">
                            <img src="{{ Storage::url($photo->file_path) }}" class="w-full h-full object-cover" alt="Foto" loading="lazy">
                            <form method="POST" action="{{ route('orders.photo.delete', [$order, $photo]) }}" onsubmit="return confirm('¿Eliminar esta foto?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition hover:bg-red-600">
                                    &times;
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-400 mb-4">No hay fotos aún.</p>
            @endif

            <form method="POST" action="{{ route('orders.photo.upload', $order) }}" enctype="multipart/form-data" class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-blue-400 transition cursor-pointer" onclick="document.getElementById('editPhotoInput').click()">
                @csrf
                <svg class="w-8 h-8 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <p class="text-sm text-gray-500">Haz clic para agregar más fotos</p>
                <p class="text-xs text-gray-400 mt-1">JPEG, PNG, WebP. Máx 10MB</p>
                <input type="file" id="editPhotoInput" name="photo" accept="image/*" class="hidden" onchange="this.form.submit()">
            </form>
        </div>
    </div>
</div>

<script>
    var modelsByBrand = @json($modelsByBrand);

    var brandSelect = document.getElementById('brand_id');
    var modelSelect = document.getElementById('model_id');
    var deviceTypeSelect = document.getElementById('device_type');
    var selectedModelId = '{{ old('model_id', $order->model_id) }}';

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
        var hasMatch = false;
        if (brandId && modelsByBrand[brandId]) {
            modelsByBrand[brandId].forEach(function(m) {
                if (!type || m.device_type === type || !m.device_type) {
                    var opt = document.createElement('option');
                    opt.value = m.id;
                    opt.textContent = m.name;
                    if (String(m.id) === String(selectedModelId)) {
                        opt.selected = true;
                        hasMatch = true;
                    }
                    modelSelect.appendChild(opt);
                }
            });
        }
        if (!hasMatch && selectedModelId) {
            modelSelect.innerHTML += '<option value="' + selectedModelId + '" selected>' + selectedModelId + '</option>';
        }
    }

    deviceTypeSelect.addEventListener('change', filterBrands);
    brandSelect.addEventListener('change', filterModels);
    filterBrands();
</script>
@endsection
