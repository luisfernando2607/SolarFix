<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Setting;

/**
 * Página pública de seguimiento: el cliente ve el estado de su orden con el enlace privado.
 * No muestra PIN, notas internas, bitácora, técnico ni datos personales.
 */
class TrackingController extends Controller
{
    public function show(string $token)
    {
        $order = Order::with(['brand', 'deviceModel', 'warranty', 'statusHistory'])
            ->where('tracking_token', $token)
            ->firstOrFail();

        return response()->view('tracking.show', [
            'order' => $order,
            'company' => [
                'name' => Setting::get('company_name', 'SolarFix'),
                'phone' => Setting::get('company_phone'),
                'address' => Setting::get('company_address'),
            ],
            'statuses' => Order::statuses(),
        ])->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
