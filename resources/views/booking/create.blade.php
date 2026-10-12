<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Agendar · {{ $company['name'] }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-100 font-sans antialiased text-gray-800">
@php $field = 'w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white'; @endphp
<main class="max-w-lg mx-auto p-4 sm:p-6">
    <header class="text-center mb-6">
        <h1 class="text-xl font-bold">{{ $company['name'] }}</h1>
        <p class="text-sm text-gray-500">Solicite una visita técnica o un turno en el taller</p>
    </header>

    @if (session('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-5 py-4 mb-4 text-green-900" role="status">
            <p class="font-semibold">¡Listo!</p>
            <p class="text-sm mt-1">{{ session('success') }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('booking.store') }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 space-y-4" novalidate>
        @csrf
        <div class="hidden" aria-hidden="true">
            <label for="website">No llenar</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <div>
            <label for="name" class="block text-sm font-medium mb-1">Nombre</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="150" autocomplete="name" class="{{ $field }}">
            @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="phone" class="block text-sm font-medium mb-1">Teléfono / WhatsApp</label>
            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" required maxlength="30" autocomplete="tel" inputmode="tel" class="{{ $field }}">
            @error('phone') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        @if ($branches->count() > 1)
            <div>
                <label for="branch_id" class="block text-sm font-medium mb-1">Sucursal</label>
                <select id="branch_id" name="branch_id" required class="{{ $field }}">
                    <option value="">Elija una…</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" {{ (int) old('branch_id') === $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                    @endforeach
                </select>
                @error('branch_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        @endif
        <div>
            <label for="type" class="block text-sm font-medium mb-1">Servicio</label>
            <select id="type" name="type" required class="{{ $field }}">
                @foreach ($types as $key => $label)
                    <option value="{{ $key }}" {{ old('type', 'home_visit') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            @error('type') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="date" class="block text-sm font-medium mb-1">Fecha</label>
                <input type="date" id="date" name="date" value="{{ old('date', today()->addDay()->toDateString()) }}" min="{{ today()->toDateString() }}" max="{{ today()->addDays(90)->toDateString() }}" required class="{{ $field }}">
                @error('date') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="time" class="block text-sm font-medium mb-1">Hora</label>
                <input type="time" id="time" name="time" value="{{ old('time', '09:00') }}" step="900" required class="{{ $field }}">
                @error('time') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label for="address" class="block text-sm font-medium mb-1">Dirección <span class="text-gray-400 font-normal">(para visitas)</span></label>
            <input type="text" id="address" name="address" value="{{ old('address') }}" maxlength="255" autocomplete="street-address" class="{{ $field }}">
            @error('address') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="details" class="block text-sm font-medium mb-1">¿Qué necesita?</label>
            <textarea id="details" name="details" rows="3" required maxlength="1000" placeholder="Equipo, marca y el problema que presenta" class="{{ $field }}">{{ old('details') }}</textarea>
            @error('details') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-3 rounded-xl">Enviar solicitud</button>
        <p class="text-xs text-gray-500 text-center">La hora queda sujeta a confirmación del taller.</p>
    </form>

    @if ($company['phone'])
        <p class="text-center text-sm text-gray-600 mt-6">¿Prefiere llamar? <a href="tel:{{ preg_replace('/[^0-9+]/', '', $company['phone']) }}" class="text-blue-600 font-medium">{{ $company['phone'] }}</a></p>
    @endif
</main>
</body>
</html>
