@extends('layouts.app')

@section('title', $checklist->name)

@php $resultStyles = ['ok' => 'peer-checked:bg-green-600 peer-checked:text-white peer-checked:border-green-600', 'damaged' => 'peer-checked:bg-red-600 peer-checked:text-white peer-checked:border-red-600', 'not_applicable' => 'peer-checked:bg-gray-500 peer-checked:text-white peer-checked:border-gray-500']; @endphp

@section('content')
<div class="p-6 max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('orders.show', $order) }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Volver a la orden
        </a>
        <h2 class="text-2xl font-bold text-gray-800 mt-2">{{ $checklist->name }}</h2>
        <p class="text-gray-500">Orden #{{ $order->order_number }} · {{ $order->client?->name ?? '—' }} ·
            {{ $order->brand?->name ?? $order->brand_text }} {{ $order->deviceModel?->name ?? $order->model_text }}</p>
    </div>

    @if ($locked)
        <div class="mb-4 rounded-lg bg-gray-50 border px-4 py-3 text-sm text-gray-600">La orden está cerrada: el checklist es de solo lectura.</div>
    @endif

    <form method="POST" action="{{ route('orders.checklists.update', [$order, $stage]) }}" class="bg-white rounded-xl shadow-sm border divide-y">
        @csrf @method('PUT')
        @foreach ($items as $item)
            @php $current = old("results.{$item->id}", optional($results[$item->id] ?? null)->result); @endphp
            <div class="p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm font-medium text-gray-800">
                        {{ $item->label }}
                        @if ($item->is_required) <span class="text-red-500">*</span> @endif
                    </p>
                    <div class="flex gap-1">
                        @foreach (\App\Models\Checklist::results() as $value => $label)
                            <label class="cursor-pointer">
                                <input type="radio" name="results[{{ $item->id }}]" value="{{ $value }}" class="peer sr-only"
                                    {{ $current === $value ? 'checked' : '' }} {{ $locked ? 'disabled' : '' }}>
                                <span class="inline-block px-3 py-1.5 rounded-lg border text-xs font-medium text-gray-600 {{ $resultStyles[$value] }}">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <input type="text" name="notes[{{ $item->id }}]" maxlength="255" placeholder="Nota (opcional)" {{ $locked ? 'disabled' : '' }}
                    value="{{ old("notes.{$item->id}", optional($results[$item->id] ?? null)->notes) }}"
                    class="mt-2 w-full px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                @error("results.{$item->id}") <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        @endforeach
        @unless ($locked)
            <div class="p-4 flex flex-wrap items-center justify-between gap-3">
                <button type="button" id="allOk" class="text-sm text-green-700 hover:underline">Marcar pendientes como OK</button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">Guardar checklist</button>
            </div>
        @endunless
    </form>
</div>

<script>
document.getElementById('allOk')?.addEventListener('click', function () {
    document.querySelectorAll('input[type=radio][value=ok]').forEach(function (radio) {
        var group = document.querySelectorAll('input[name="' + radio.name + '"]');
        if (!Array.prototype.some.call(group, function (r) { return r.checked; })) radio.checked = true;
    });
});
</script>
@endsection
