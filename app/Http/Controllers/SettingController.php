<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\WhatsAppMessage;
use App\Support\PaymentMethods;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private const FIELDS = [
        'company_name' => 'general',
        'company_tax_id' => 'general',
        'company_phone' => 'general',
        'company_email' => 'general',
        'company_address' => 'general',
        'default_warranty_days' => 'orders',
        'warranty_alert_days' => 'warranty',
        'iva_percent' => 'billing',
        'prices_include_iva' => 'billing',
        'stale_order_days' => 'alerts',
        'pickup_reminder_days' => 'alerts',
        'approval_alert_hours' => 'alerts',
        'commission_base' => 'commissions',
        'require_quality_check' => 'orders',
        'order_prefix' => 'orders',
        'invoice_prefix' => 'billing',
        'currency_symbol' => 'general',
        'diagnosis_cost_default' => 'orders',
        'maintenance_due_days' => 'alerts',
        'ticket_terms' => 'orders',
        'online_approval' => 'portal',
        'customer_survey' => 'portal',
        'online_booking' => 'portal',
        'require_admin_2fa' => 'security',
    ];

    public function edit()
    {
        $settings = [];
        foreach (array_keys(self::FIELDS) as $key) {
            $settings[$key] = Setting::get($key);
        }

        return view('settings.edit', [
            'settings' => $settings,
            'paymentMethods' => PaymentMethods::all(),
            'templates' => collect(WhatsAppMessage::TEMPLATES)->map(fn ($default, $type) => (string) Setting::get('wa_template_' . $type, '')),
        ]);
    }

    /**
     * Una variable mal escrita (ej. {clinete}) llegaría tal cual al cliente: se rechaza.
     */
    private function rejectUnknownVariables(array $templates): void
    {
        $errors = [];
        foreach ($templates as $type => $text) {
            if (! isset(WhatsAppMessage::VARIABLES[$type]) || ! is_string($text)) {
                continue;
            }
            preg_match_all('/\{([^{}\s]+)\}/', $text, $matches);
            $unknown = array_diff(array_unique($matches[1]), WhatsAppMessage::VARIABLES[$type]);
            if ($unknown) {
                $errors["wa_templates.{$type}"] = 'Variables que no existen en este mensaje: {' . implode('}, {', $unknown) . '}.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Filas del formulario → [clave => nombre]. Efectivo siempre queda; una fila sin nombre se quita.
     * Las claves existentes se conservan (los pagos guardados las usan); las nuevas salen del nombre.
     */
    private function paymentMethods(array $rows): array
    {
        $methods = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $key = (string) ($row['key'] ?? '');

            if ($key === PaymentMethods::CASH) {
                $methods[$key] = $label !== '' ? $label : PaymentMethods::DEFAULTS[PaymentMethods::CASH];
                continue;
            }
            if ($label === '') {
                continue;
            }
            if ($key === '') {
                $base = Str::limit(Str::slug($label, '_'), 24, '') ?: 'metodo';
                $key = $base;
                for ($i = 2; isset($methods[$key]) || $key === PaymentMethods::CASH; $i++) {
                    $key = $base . '_' . $i;
                }
            }
            $methods[$key] = $label;
        }

        return [PaymentMethods::CASH => $methods[PaymentMethods::CASH] ?? PaymentMethods::DEFAULTS[PaymentMethods::CASH]] + $methods;
    }

    public function update(Request $request)
    {
        // Los prefijos se guardan en mayúsculas.
        $request->merge([
            'order_prefix' => strtoupper(trim((string) $request->input('order_prefix'))),
            'invoice_prefix' => strtoupper(trim((string) $request->input('invoice_prefix'))),
        ]);

        $this->rejectUnknownVariables($request->input('wa_templates', []));

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'company_tax_id' => ['nullable', 'string', 'max:20'],
            'company_phone' => ['nullable', 'string', 'max:30'],
            'company_email' => ['nullable', 'email', 'max:120'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'default_warranty_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'warranty_alert_days' => ['required', 'integer', 'min:1', 'max:365'],
            'iva_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'prices_include_iva' => ['boolean'],
            'stale_order_days' => ['required', 'integer', 'min:1', 'max:365'],
            'pickup_reminder_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'approval_alert_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'commission_base' => ['required', 'in:service,total'],
            'require_quality_check' => ['boolean'],
            'online_approval' => ['boolean'],
            'customer_survey' => ['boolean'],
            'online_booking' => ['boolean'],
            'require_admin_2fa' => ['boolean'],
            // El número de orden (prefijo-AAAAMMDD-0001) cabe en 20 caracteres con hasta 6 de prefijo.
            'order_prefix' => ['required', 'regex:/^[A-Z0-9]{1,6}$/'],
            'invoice_prefix' => ['required', 'regex:/^[A-Z0-9]{1,8}$/'],
            'currency_symbol' => ['required', 'string', 'max:4'],
            'diagnosis_cost_default' => ['required', 'numeric', 'min:0', 'max:99999'],
            'maintenance_due_days' => ['required', 'integer', 'min:1', 'max:90'],
            'ticket_terms' => ['nullable', 'string', 'max:600'],
            'payment_methods' => ['required', 'array', 'max:20'],
            'payment_methods.*.key' => ['nullable', 'string', 'max:30'],
            'payment_methods.*.label' => ['nullable', 'string', 'max:40'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'wa_templates' => ['nullable', 'array'],
            'wa_templates.*' => ['nullable', 'string', 'max:2000'],
        ], [
            'order_prefix.regex' => 'El prefijo de orden debe tener de 1 a 6 letras o números, sin espacios.',
            'invoice_prefix.regex' => 'El prefijo de factura debe tener de 1 a 8 letras o números, sin espacios.',
        ]);

        foreach (self::FIELDS as $key => $group) {
            $isFlag = in_array($key, ['prices_include_iva', 'require_quality_check', 'online_approval', 'customer_survey', 'online_booking', 'require_admin_2fa'], true);
            Setting::put($key, $isFlag ? (string) (int) $request->boolean($key) : (string) ($validated[$key] ?? ''), $group);
        }

        if ($request->hasFile('logo')) {
            try {
                \App\Support\Branding::storeLogo($request->file('logo'));
            } catch (\DomainException $e) {
                return back()->withInput()->withErrors(['logo' => $e->getMessage()]);
            }
        } elseif ($request->boolean('remove_logo')) {
            \App\Support\Branding::removeLogo();
        }

        Setting::put('payment_methods', json_encode($this->paymentMethods($validated['payment_methods']), JSON_UNESCAPED_UNICODE), 'billing');

        // Textos de WhatsApp: vacío o igual al original = se usa el texto por defecto.
        foreach (WhatsAppMessage::TEMPLATES as $type => $default) {
            $text = trim(str_replace("\r\n", "\n", (string) ($validated['wa_templates'][$type] ?? '')));
            Setting::put('wa_template_' . $type, $text === $default ? '' : $text, 'whatsapp');
        }

        return redirect()->route('settings.edit')->with('success', 'Configuración guardada.');
    }
}
