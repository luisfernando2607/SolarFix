<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Setting;

/**
 * Datos del negocio para documentos y páginas públicas. Teléfono, dirección y correo
 * salen de la sucursal cuando los tiene; si no, de Configuración.
 */
class Company
{
    public static function info(?int $branchId = null): array
    {
        $branch = $branchId && Branch::isMultiple() ? Branch::find($branchId) : null;

        return [
            'name' => Setting::get('company_name', 'SolarFix'),
            'tax_id' => Setting::get('company_tax_id'),
            'branch' => $branch?->name,
            'phone' => $branch?->phone ?: Setting::get('company_phone'),
            'address' => $branch?->address ?: Setting::get('company_address'),
            'email' => $branch?->email ?: Setting::get('company_email'),
            'terms' => Setting::get('ticket_terms'),
        ];
    }
}
