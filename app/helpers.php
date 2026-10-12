<?php

use App\Models\Setting;

if (! function_exists('money')) {
    /**
     * Monto con el símbolo de moneda configurado, ej. "$1,234.50".
     */
    function money($amount): string
    {
        return Setting::get('currency_symbol', '$') . number_format((float) $amount, 2);
    }
}
