@php
    $input = 'w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none';
    $value = fn ($key, $default = '') => old($key, isset($part) ? $part->{$key} : $default);
@endphp
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
        <input type="text" id="name" name="name" value="{{ $value('name') }}" required class="{{ $input }} @error('name') border-red-500 @enderror"
            placeholder="Ej. Pantalla Samsung A15 original">
        @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Código</label>
        <input type="text" id="code" name="code" value="{{ $value('code') }}" class="{{ $input }} @error('code') border-red-500 @enderror">
        @error('code') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
        <input type="text" id="category" name="category" value="{{ $value('category') }}" list="categoryList" class="{{ $input }}"
            placeholder="Ej. Pantallas, Baterías, Compresores">
        <datalist id="categoryList">
            @foreach ($categories as $category)
                <option value="{{ $category }}">
            @endforeach
        </datalist>
    </div>
    <div>
        <label for="compatible_brand" class="block text-sm font-medium text-gray-700 mb-1">Marca compatible</label>
        <input type="text" id="compatible_brand" name="compatible_brand" value="{{ $value('compatible_brand') }}" class="{{ $input }}">
    </div>
    <div>
        <label for="compatible_model" class="block text-sm font-medium text-gray-700 mb-1">Modelo compatible</label>
        <input type="text" id="compatible_model" name="compatible_model" value="{{ $value('compatible_model') }}" class="{{ $input }}">
    </div>
    <div>
        <label for="cost_price" class="block text-sm font-medium text-gray-700 mb-1">Precio de costo *</label>
        <input type="number" step="0.01" min="0" id="cost_price" name="cost_price" value="{{ $value('cost_price', '0') }}" required class="{{ $input }} @error('cost_price') border-red-500 @enderror">
        @error('cost_price') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="sale_price" class="block text-sm font-medium text-gray-700 mb-1">Precio de venta *</label>
        <input type="number" step="0.01" min="0" id="sale_price" name="sale_price" value="{{ $value('sale_price', '0') }}" required class="{{ $input }} @error('sale_price') border-red-500 @enderror">
        @error('sale_price') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="min_stock" class="block text-sm font-medium text-gray-700 mb-1">Stock mínimo *</label>
        <input type="number" min="0" id="min_stock" name="min_stock" value="{{ $value('min_stock', '1') }}" required class="{{ $input }} @error('min_stock') border-red-500 @enderror">
        @error('min_stock') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="location" class="block text-sm font-medium text-gray-700 mb-1">Ubicación</label>
        <input type="text" id="location" name="location" value="{{ $value('location') }}" class="{{ $input }}" placeholder="Ej. Estante A-3">
    </div>
    @unless (isset($part))
        <div>
            <label for="initial_stock" class="block text-sm font-medium text-gray-700 mb-1">Stock inicial</label>
            <input type="number" min="0" id="initial_stock" name="initial_stock" value="{{ old('initial_stock', 0) }}" class="{{ $input }}">
            <p class="text-xs text-gray-500 mt-1">Se registra como entrada al precio de costo.</p>
        </div>
    @endunless
</div>
<label class="flex items-center gap-2 mt-4">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" {{ old('is_active', isset($part) ? $part->is_active : true) ? 'checked' : '' }}
        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
    <span class="text-sm text-gray-700">Repuesto activo</span>
</label>
