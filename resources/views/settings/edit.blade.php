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

    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-1">Negocio</h3>
            <p class="text-xs text-gray-500 mb-4">Aparece en tickets, facturas, comprobantes, la página del cliente y los mensajes de WhatsApp.</p>
            <div class="mb-4 flex flex-wrap items-center gap-4">
                @if ($logo = \App\Support\Branding::logoDataUri())
                    <img src="{{ $logo }}" alt="Logo actual" class="h-16 w-auto max-w-40 object-contain border rounded-lg p-1 bg-white">
                @endif
                <div>
                    <label for="logo" class="block text-sm font-medium text-gray-700 mb-1">Logo</label>
                    <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp"
                        class="block text-sm text-gray-700 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-gray-100 file:text-gray-700">
                    <p class="text-xs text-gray-500 mt-1">PNG con fondo transparente, de preferencia. Se ajusta solo.</p>
                    @error('logo') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                @if ($logo)
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="remove_logo" value="1" class="w-4 h-4 border-gray-300 rounded"> Quitar logo
                    </label>
                @endif
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach (['company_name' => 'Nombre *', 'company_tax_id' => 'RUC', 'company_phone' => 'Teléfono', 'company_email' => 'Email', 'company_address' => 'Dirección'] as $key => $label)
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
            <h3 class="text-base font-semibold text-gray-800 mb-1">Numeración y montos</h3>
            <p class="text-xs text-gray-500 mb-4">Al cambiar un prefijo, la numeración de los nuevos documentos empieza con él.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="order_prefix" class="block text-sm font-medium text-gray-700 mb-1">Prefijo de orden *</label>
                    <input id="order_prefix" name="order_prefix" type="text" maxlength="6" style="text-transform:uppercase" value="{{ old('order_prefix', $settings['order_prefix']) }}" required
                        class="{{ $input }} @error('order_prefix') border-red-500 @enderror">
                    <p class="text-xs text-gray-500 mt-1">Ej. SF → SF-GYE-20261012-0001 (GYE = código de la sucursal)</p>
                    @error('order_prefix') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="invoice_prefix" class="block text-sm font-medium text-gray-700 mb-1">Prefijo de factura *</label>
                    <input id="invoice_prefix" name="invoice_prefix" type="text" maxlength="8" style="text-transform:uppercase" value="{{ old('invoice_prefix', $settings['invoice_prefix']) }}" required
                        class="{{ $input }} @error('invoice_prefix') border-red-500 @enderror">
                    <p class="text-xs text-gray-500 mt-1">Ej. FAC → FAC-0001</p>
                    @error('invoice_prefix') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="currency_symbol" class="block text-sm font-medium text-gray-700 mb-1">Símbolo de moneda *</label>
                    <input id="currency_symbol" name="currency_symbol" type="text" maxlength="4" value="{{ old('currency_symbol', $settings['currency_symbol']) }}" required
                        class="{{ $input }} @error('currency_symbol') border-red-500 @enderror">
                    
                    @error('currency_symbol') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="diagnosis_cost_default" class="block text-sm font-medium text-gray-700 mb-1">Costo de diagnóstico por defecto *</label>
                    <input id="diagnosis_cost_default" name="diagnosis_cost_default" type="number" step="0.01" min="0" value="{{ old('diagnosis_cost_default', $settings['diagnosis_cost_default']) }}" required
                        class="{{ $input }} @error('diagnosis_cost_default') border-red-500 @enderror">
                    <p class="text-xs text-gray-500 mt-1">Se propone al crear una orden.</p>
                    @error('diagnosis_cost_default') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-1">Métodos de pago</h3>
            <p class="text-xs text-gray-500 mb-4">Efectivo es fijo (lo usa el cuadre de caja). Deja un nombre vacío para quitar un método; los pagos ya registrados conservan su nombre.</p>
            @php
                $methodRows = old('payment_methods', collect($paymentMethods)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all());
                $methodRows = array_merge($methodRows, array_fill(0, 2, ['key' => '', 'label' => '']));
            @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach ($methodRows as $i => $row)
                    <div class="flex items-center gap-2">
                        <input type="hidden" name="payment_methods[{{ $i }}][key]" value="{{ $row['key'] }}">
                        <input type="text" name="payment_methods[{{ $i }}][label]" value="{{ $row['label'] }}" maxlength="40"
                            placeholder="{{ $row['key'] ? '' : 'Nuevo método (ej. Deuna)' }}" {{ $row['key'] === 'cash' ? 'required' : '' }}
                            class="{{ $input }}">
                        @if ($row['key'] === 'cash')
                            <span class="text-xs text-gray-400 whitespace-nowrap">fijo</span>
                        @endif
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
            <div class="mt-4">
                <label for="ticket_terms" class="block text-sm font-medium text-gray-700 mb-1">Condiciones impresas en el ticket de recepción</label>
                <textarea id="ticket_terms" name="ticket_terms" rows="3" maxlength="600" placeholder="Presente este ticket para retirar su equipo."
                    class="{{ $input }} @error('ticket_terms') border-red-500 @enderror">{{ old('ticket_terms', $settings['ticket_terms']) }}</textarea>
                <p class="text-xs text-gray-500 mt-1">Ej. plazo para retirar el equipo, política de garantía o de equipos abandonados.</p>
                @error('ticket_terms') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mt-4 max-w-xs">
                <label for="budget_validity_days" class="block text-sm font-medium text-gray-700 mb-1">Validez del presupuesto (días)</label>
                <input type="number" min="1" max="365" id="budget_validity_days" name="budget_validity_days"
                    value="{{ old('budget_validity_days', $settings['budget_validity_days'] ?? 15) }}" class="{{ $input }} @error('budget_validity_days') border-red-500 @enderror">
                @error('budget_validity_days') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-1">Página del cliente</h3>
            <p class="text-xs text-gray-500 mb-3">El enlace de seguimiento (QR del ticket y mensajes de WhatsApp).</p>
            @foreach ([
                'online_approval' => ['Permitir que el cliente apruebe o rechace el presupuesto en línea', 'La orden avanza sola y el técnico recibe un aviso.'],
                'customer_survey' => ['Pedir una calificación del servicio después de la entrega', 'Las calificaciones de 3 estrellas o menos avisan a la administración.'],
                'online_booking' => ['Permitir que los clientes soliciten citas en línea', 'Página pública para compartir en redes y WhatsApp: ' . route('booking.create')],
            ] as $key => [$label, $help])
                <label class="flex items-start gap-2 {{ $loop->first ? '' : 'mt-3' }}">
                    <input type="hidden" name="{{ $key }}" value="0">
                    <input type="checkbox" name="{{ $key }}" value="1" {{ old($key, $settings[$key] ?? '1') ? 'checked' : '' }}
                        class="w-4 h-4 mt-0.5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <span class="text-sm text-gray-700">{{ $label }}<span class="block text-xs text-gray-500">{{ $help }}</span></span>
                </label>
            @endforeach
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-1">Correo a clientes</h3>
            <p class="text-xs text-gray-500 mb-3">Además de WhatsApp, el cliente con correo recibe el presupuesto, el aviso de equipo listo y el de entrega (mismo texto de las plantillas, con el logo y el enlace de seguimiento).</p>
            @if (in_array(config('mail.default'), ['log', 'array'], true))
                <p class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    El servidor todavía no tiene correo configurado (<code>MAIL_MAILER={{ config('mail.default') }}</code>): los correos quedan en el registro y no salen.
                    Configura <code>MAIL_MAILER=smtp</code>, <code>MAIL_HOST</code>, <code>MAIL_PORT</code>, <code>MAIL_USERNAME</code>, <code>MAIL_PASSWORD</code> y <code>MAIL_FROM_ADDRESS</code> en el .env (Gmail, el correo de tu dominio, Brevo…).
                </p>
            @endif
            <label class="flex items-start gap-2">
                <input type="hidden" name="email_notifications" value="0">
                <input type="checkbox" name="email_notifications" value="1" {{ old('email_notifications', $settings['email_notifications'] ?? '0') ? 'checked' : '' }}
                    class="w-4 h-4 mt-0.5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <span class="text-sm text-gray-700">Enviar avisos por correo automáticamente<span class="block text-xs text-gray-500">Desde cada orden también se puede enviar a mano.</span></span>
            </label>
            <button type="submit" form="testEmailForm" class="mt-3 bg-white hover:bg-gray-50 text-gray-700 border px-3 py-1.5 rounded-lg text-sm">Enviarme un correo de prueba</button>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-3">Seguridad</h3>
            <label class="flex items-start gap-2">
                <input type="hidden" name="require_admin_2fa" value="0">
                <input type="checkbox" name="require_admin_2fa" value="1" {{ old('require_admin_2fa', $settings['require_admin_2fa'] ?? '0') ? 'checked' : '' }}
                    class="w-4 h-4 mt-0.5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <span class="text-sm text-gray-700">
                    Exigir verificación en dos pasos a los administradores
                    <span class="block text-xs text-gray-500">Al entrar, quien no la tenga deberá configurarla antes de continuar. Actívala primero en tu cuenta.</span>
                </span>
            </label>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-1">Limpieza automática</h3>
            <p class="text-xs text-gray-500 mb-4">Cada noche se borran los registros más antiguos que estos días, para que la base no crezca sin límite. 0 = conservar siempre. Órdenes, pagos, facturas y clientes nunca se borran.</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach ([
                    'notification_retention_days' => ['Notificaciones leídas', 90],
                    'email_log_retention_days' => ['Registro de correos', 365],
                    'audit_retention_days' => ['Auditoría', 730],
                ] as $key => [$label, $default])
                    <div>
                        <label for="{{ $key }}" class="block text-sm font-medium text-gray-700 mb-1">{{ $label }} (días)</label>
                        <input type="number" min="0" max="3650" id="{{ $key }}" name="{{ $key }}" value="{{ old($key, $settings[$key] ?? $default) }}"
                            class="{{ $input }} @error($key) border-red-500 @enderror">
                        @error($key) <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-3">Control de calidad</h3>
            <label class="flex items-start gap-2">
                <input type="hidden" name="require_quality_check" value="0">
                <input type="checkbox" name="require_quality_check" value="1" {{ old('require_quality_check', $settings['require_quality_check'] ?? '0') ? 'checked' : '' }}
                    class="w-4 h-4 mt-0.5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <span class="text-sm text-gray-700">
                    Exigir el checklist de control de calidad para marcar una orden como "Listo"
                    <span class="block text-xs text-gray-500">Solo aplica a los tipos de equipo que tienen ese checklist.</span>
                </span>
            </label>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-1">Comisiones</h3>
            <p class="text-xs text-gray-500 mb-4">Sobre qué monto se calcula la comisión porcentual de los técnicos (se configura en cada usuario).</p>
            @foreach (\App\Models\Commission::bases() as $key => $label)
                <label class="flex items-center gap-2 mb-2">
                    <input type="radio" name="commission_base" value="{{ $key }}" {{ old('commission_base', $settings['commission_base'] ?? 'service') === $key ? 'checked' : '' }}
                        class="w-4 h-4 text-blue-600 border-gray-300">
                    <span class="text-sm text-gray-700">{{ $label }}</span>
                </label>
            @endforeach
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
                    <label for="pickup_reminder_days" class="block text-sm font-medium text-gray-700 mb-1">Recordar retiro después de (días lista)</label>
                    <input type="number" min="1" max="365" id="pickup_reminder_days" name="pickup_reminder_days"
                        value="{{ old('pickup_reminder_days', $settings['pickup_reminder_days'] ?? 7) }}"
                        class="{{ $input }} @error('pickup_reminder_days') border-red-500 @enderror">
                    @error('pickup_reminder_days') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="warranty_alert_days" class="block text-sm font-medium text-gray-700 mb-1">Avisar garantías que vencen en (días) *</label>
                    <input type="number" min="1" id="warranty_alert_days" name="warranty_alert_days"
                        value="{{ old('warranty_alert_days', $settings['warranty_alert_days']) }}" required
                        class="{{ $input }} @error('warranty_alert_days') border-red-500 @enderror">
                    @error('warranty_alert_days') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="maintenance_due_days" class="block text-sm font-medium text-gray-700 mb-1">Avisar mantenimientos (días antes) *</label>
                    <input id="maintenance_due_days" name="maintenance_due_days" type="number" min="1" max="90" value="{{ old('maintenance_due_days', $settings['maintenance_due_days']) }}" required
                        class="{{ $input }} @error('maintenance_due_days') border-red-500 @enderror">
                    
                    @error('maintenance_due_days') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
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

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-1">Mensajes de WhatsApp</h3>
            <p class="text-xs text-gray-500 mb-4">
                Deja un mensaje vacío para usar el texto original. Las variables van entre llaves, ej. <code>{cliente}</code>.
                Una línea que solo tiene una variable sin valor (ej. <code>{diagnostico}</code> sin diagnóstico) no se envía.
                Usa *asteriscos* para negrita.
            </p>
            <div class="space-y-5">
                @foreach (\App\Services\WhatsAppMessage::TEMPLATES as $type => $default)
                    <div>
                        <label for="wa_{{ $type }}" class="block text-sm font-medium text-gray-700 mb-1">{{ \App\Services\WhatsAppMessage::TEMPLATE_LABELS[$type] }}</label>
                        <textarea id="wa_{{ $type }}" name="wa_templates[{{ $type }}]" rows="6" maxlength="2000" placeholder="{{ $default }}"
                            class="{{ $input }} font-mono text-xs @error('wa_templates.' . $type) border-red-500 @enderror">{{ old('wa_templates.' . $type, $templates[$type]) }}</textarea>
                        @error('wa_templates.' . $type) <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        <p class="text-xs text-gray-500 mt-1">Variables:
                            @foreach (\App\Services\WhatsAppMessage::VARIABLES[$type] as $variable)
                                <code class="bg-gray-100 px-1 rounded">{{ '{' . $variable . '}' }}</code>
                            @endforeach
                        </p>
                    </div>
                @endforeach
            </div>
        </div>

        <div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">
                Guardar configuración
            </button>
        </div>
    </form>
    <form id="testEmailForm" method="POST" action="{{ route('settings.test-email') }}" class="hidden">@csrf</form>
</div>
@endsection
