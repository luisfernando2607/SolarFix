{{-- Cambios de un registro de auditoría: antes → después --}}
@php
    $old = $log->old_values ?? [];
    $new = $log->new_values ?? [];
    $fields = array_unique(array_merge(array_keys($old), array_keys($new)));
    $show = fn ($v) => $v === null || $v === '' ? '—' : (is_bool($v) ? ($v ? 'sí' : 'no') : (is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v));
@endphp
@if ($log->event === 'updated' && $fields)
    <ul class="space-y-0.5">
        @foreach ($fields as $field)
            <li>
                <span class="text-gray-500">{{ \App\Models\AuditLog::fieldLabel($field) }}:</span>
                <span class="line-through text-red-600/80">{{ \Illuminate\Support\Str::limit($show($old[$field] ?? null), 60) }}</span>
                → <span class="text-green-700">{{ \Illuminate\Support\Str::limit($show($new[$field] ?? null), 60) }}</span>
            </li>
        @endforeach
    </ul>
@elseif ($fields)
    <details>
        <summary class="cursor-pointer text-blue-600">{{ count($fields) }} campos</summary>
        <ul class="mt-1 space-y-0.5">
            @foreach ($log->event === 'deleted' ? $old : $new as $field => $value)
                <li><span class="text-gray-500">{{ \App\Models\AuditLog::fieldLabel($field) }}:</span> {{ \Illuminate\Support\Str::limit($show($value), 80) }}</li>
            @endforeach
        </ul>
    </details>
@endif
