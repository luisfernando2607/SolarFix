<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private const FIELDS = [
        'company_name' => 'general',
        'company_phone' => 'general',
        'company_email' => 'general',
        'company_address' => 'general',
        'default_warranty_days' => 'orders',
        'warranty_alert_days' => 'warranty',
        'iva_percent' => 'billing',
        'prices_include_iva' => 'billing',
        'stale_order_days' => 'alerts',
        'approval_alert_hours' => 'alerts',
        'commission_base' => 'commissions',
        'require_quality_check' => 'orders',
        'order_prefix' => 'orders',
        'invoice_prefix' => 'billing',
        'currency_symbol' => 'general',
        'diagnosis_cost_default' => 'orders',
        'maintenance_due_days' => 'alerts',
    ];

    public function edit()
    {
        $settings = [];
        foreach (array_keys(self::FIELDS) as $key) {
            $settings[$key] = Setting::get($key);
        }

        return view('settings.edit', compact('settings'));
    }

    public function update(Request $request)
    {
        // Los prefijos se guardan en mayúsculas.
        $request->merge([
            'order_prefix' => strtoupper(trim((string) $request->input('order_prefix'))),
            'invoice_prefix' => strtoupper(trim((string) $request->input('invoice_prefix'))),
        ]);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'company_phone' => ['nullable', 'string', 'max:30'],
            'company_email' => ['nullable', 'email', 'max:120'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'default_warranty_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'warranty_alert_days' => ['required', 'integer', 'min:1', 'max:365'],
            'iva_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'prices_include_iva' => ['boolean'],
            'stale_order_days' => ['required', 'integer', 'min:1', 'max:365'],
            'approval_alert_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'commission_base' => ['required', 'in:service,total'],
            'require_quality_check' => ['boolean'],
            // El número de orden (prefijo-AAAAMMDD-0001) cabe en 20 caracteres con hasta 6 de prefijo.
            'order_prefix' => ['required', 'regex:/^[A-Z0-9]{1,6}$/'],
            'invoice_prefix' => ['required', 'regex:/^[A-Z0-9]{1,8}$/'],
            'currency_symbol' => ['required', 'string', 'max:4'],
            'diagnosis_cost_default' => ['required', 'numeric', 'min:0', 'max:99999'],
            'maintenance_due_days' => ['required', 'integer', 'min:1', 'max:90'],
        ], [
            'order_prefix.regex' => 'El prefijo de orden debe tener de 1 a 6 letras o números, sin espacios.',
            'invoice_prefix.regex' => 'El prefijo de factura debe tener de 1 a 8 letras o números, sin espacios.',
        ]);

        foreach (self::FIELDS as $key => $group) {
            $isFlag = in_array($key, ['prices_include_iva', 'require_quality_check'], true);
            Setting::put($key, $isFlag ? (string) (int) $request->boolean($key) : (string) ($validated[$key] ?? ''), $group);
        }

        return redirect()->route('settings.edit')->with('success', 'Configuración guardada.');
    }
}
