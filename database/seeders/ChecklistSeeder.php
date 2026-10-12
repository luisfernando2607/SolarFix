<?php

namespace Database\Seeders;

use App\Models\Checklist;
use Illuminate\Database\Seeder;

/**
 * Checklists base por tipo de equipo. Solo crea los que no existen; no toca los editados.
 */
class ChecklistSeeder extends Seeder
{
    public function run(): void
    {
        $checklists = [
            ['celular', 'reception', 'Checklist recepción celular', [
                'Pantalla (sin rayaduras / sin golpes)', 'Vidrio trasero / carcasa', 'Botones físicos (encendido, volumen)',
                'Puerto de carga (sin daño visible)', 'SIM card presente', 'Accesorios coinciden con los declarados',
            ]],
            ['celular', 'diagnosis', 'Checklist diagnóstico celular', [
                'Pantalla / táctil funciona', 'Cámara trasera', 'Cámara frontal', 'Altavoz / bocina', 'Micrófono',
                'Puerto de carga (carga correctamente)', 'Batería (mantiene carga)', 'Conectividad WiFi / datos',
                'Software (enciende, sin bootloop)', 'Huella / Face ID',
            ]],
            ['celular', 'delivery', 'Control de calidad celular', [
                'Falla reportada resuelta', 'Pantalla / táctil funciona', 'Carga correctamente', 'Cámaras',
                'Audio (altavoz y micrófono)', 'Sin daños nuevos respecto a la recepción',
            ]],
            ['aire_split', 'reception', 'Checklist recepción aire split', [
                'Unidad interior sin daño visible', 'Unidad exterior sin daño visible', 'Control remoto presente y funciona',
                'Instalación eléctrica en buen estado', 'Tubería / drenaje sin obstrucción',
            ]],
            ['aire_split', 'diagnosis', 'Checklist diagnóstico aire split', [
                'Filtros (limpieza)', 'Nivel de gas refrigerante', 'Compresor (arranca y funciona)', 'Ventilador unidad interior',
                'Ventilador unidad exterior', 'Drenaje (sin obstrucción)', 'Ruidos anómalos', 'Temperatura alcanzada (enfría/calienta)',
                'Termostato / tarjeta electrónica', 'Cableado eléctrico interno',
            ]],
            ['aire_split', 'delivery', 'Control de calidad aire split', [
                'Falla reportada resuelta', 'Enfría / calienta correctamente', 'Sin fugas ni goteo', 'Sin ruidos anómalos',
                'Control remoto funciona',
            ]],
            ['pc', 'reception', 'Checklist recepción PC / Laptop', [
                'Carcasa / chasis sin daño físico', 'Pantalla sin rayaduras graves', 'Teclado completo y sin teclas sueltas',
                'Puertos USB / HDMI sin daño visible', 'Cargador / fuente de poder presente',
            ]],
            ['pc', 'diagnosis', 'Checklist diagnóstico PC / Laptop', [
                'Enciende y llega al sistema operativo', 'Pantalla (sin líneas / parpadeo)', 'Teclado funciona', 'Touchpad funciona',
                'Almacenamiento (HDD / SSD detectado)', 'RAM (cantidad y velocidad)', 'Batería (carga y mantiene)',
                'Puertos USB funcionan', 'WiFi / Bluetooth', 'Temperatura bajo carga (no sobrecalienta)',
            ]],
            ['pc', 'delivery', 'Control de calidad PC / Laptop', [
                'Falla reportada resuelta', 'Enciende y llega al sistema operativo', 'Teclado y touchpad funcionan',
                'Carga correctamente', 'Sin daños nuevos respecto a la recepción',
            ]],
        ];

        foreach ($checklists as [$deviceType, $stage, $name, $items]) {
            $checklist = Checklist::firstOrCreate(['device_type' => $deviceType, 'stage' => $stage], ['name' => $name, 'is_active' => true]);

            if ($checklist->wasRecentlyCreated) {
                foreach ($items as $i => $label) {
                    $checklist->items()->create(['label' => $label, 'sort_order' => $i + 1, 'is_required' => true]);
                }
            }
        }
    }
}
