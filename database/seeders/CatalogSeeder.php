<?php

namespace Database\Seeders;

use App\Models\Accessory;
use App\Models\DeviceBrand;
use App\Models\DeviceModel;
use Illuminate\Database\Seeder;

/**
 * Catálogos base: accesorios, marcas y modelos. Es idempotente y no modifica
 * registros existentes, así que puede ejecutarse sobre una base con datos reales.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Solo celular', 'Funda protectora', 'Cargador', 'Cable USB', 'Cargador y cable',
            'Auriculares', 'Batería adicional', 'Control remoto', 'Caja del equipo',
            'Kit completo (cargador + cable + funda)',
        ] as $name) {
            Accessory::firstOrCreate(['name' => $name], ['is_active' => true]);
        }

        $consolidated = [
            'Samsung' => ['celular', 'tablet', 'pc', 'televisor', 'lavador', 'aire_split'],
            'Motorola' => ['celular'],
            'iPhone (Apple)' => ['celular', 'tablet', 'pc'],
            'Xiaomi' => ['celular', 'tablet', 'pc', 'televisor'],
            'Redmi' => ['celular'],
            'POCO' => ['celular'],
            'Huawei' => ['celular', 'tablet', 'pc'],
            'Honor' => ['celular', 'tablet', 'pc'],
            'LG' => ['celular', 'pc', 'televisor', 'lavador', 'aire_split'],
            'Nokia' => ['celular'],
            'Otras marcas' => ['celular', 'tablet', 'pc', 'televisor', 'lavador', 'aire_split', 'otro'],
            'Midea' => ['aire_split'],
            'Gree' => ['aire_split'],
            'Carrier' => ['aire_split'],
            'Daikin' => ['aire_split'],
            'Mirage' => ['aire_split'],
            'Klimaire' => ['aire_split'],
        ];

        foreach ($consolidated as $name => $types) {
            DeviceBrand::firstOrCreate(['name' => $name], ['device_types' => $types, 'is_active' => true]);
        }

        $modelsByBrand = [
            'Samsung' => [
                ['name' => 'Galaxy S25 Ultra', 'type' => 'celular'],
                ['name' => 'Galaxy S25', 'type' => 'celular'],
                ['name' => 'Galaxy S24', 'type' => 'celular'],
                ['name' => 'Galaxy A55', 'type' => 'celular'],
                ['name' => 'Galaxy A35', 'type' => 'celular'],
                ['name' => 'Galaxy A15', 'type' => 'celular'],
                ['name' => 'Galaxy A05', 'type' => 'celular'],
                ['name' => 'Galaxy Tab S10 Ultra', 'type' => 'tablet'],
                ['name' => 'Galaxy Tab S9 FE', 'type' => 'tablet'],
                ['name' => 'Galaxy Tab A9', 'type' => 'tablet'],
                ['name' => 'Galaxy Book4 Ultra', 'type' => 'pc'],
                ['name' => 'Galaxy Book4 Pro', 'type' => 'pc'],
                ['name' => 'Smart TV 4K 43"', 'type' => 'televisor'],
                ['name' => 'Smart TV 4K 55"', 'type' => 'televisor'],
                ['name' => 'Smart TV 4K 65"', 'type' => 'televisor'],
                ['name' => 'Crystal UHD 75"', 'type' => 'televisor'],
                ['name' => 'Lavadora Digital 15kg', 'type' => 'lavador'],
                ['name' => 'Lavadora Digital 19kg', 'type' => 'lavador'],
                ['name' => 'Split Inverter 12000BTU', 'type' => 'aire_split'],
                ['name' => 'Split Inverter 24000BTU', 'type' => 'aire_split'],
            ],
            'Motorola' => [
                ['name' => 'Moto G85', 'type' => 'celular'],
                ['name' => 'Moto G54', 'type' => 'celular'],
                ['name' => 'Moto G24', 'type' => 'celular'],
                ['name' => 'Moto G14', 'type' => 'celular'],
                ['name' => 'Edge 50 Pro', 'type' => 'celular'],
                ['name' => 'Edge 50 Fusion', 'type' => 'celular'],
                ['name' => 'Edge 50 Neo', 'type' => 'celular'],
            ],
            'iPhone (Apple)' => [
                ['name' => 'iPhone 16 Pro Max', 'type' => 'celular'],
                ['name' => 'iPhone 16 Pro', 'type' => 'celular'],
                ['name' => 'iPhone 16', 'type' => 'celular'],
                ['name' => 'iPhone 15', 'type' => 'celular'],
                ['name' => 'iPhone 14', 'type' => 'celular'],
                ['name' => 'iPhone SE', 'type' => 'celular'],
                ['name' => 'iPad Pro M4 13"', 'type' => 'tablet'],
                ['name' => 'iPad Air M2 11"', 'type' => 'tablet'],
                ['name' => 'iPad 10ma Gen', 'type' => 'tablet'],
                ['name' => 'iPad mini', 'type' => 'tablet'],
                ['name' => 'MacBook Air M4', 'type' => 'pc'],
                ['name' => 'MacBook Pro M4 14"', 'type' => 'pc'],
                ['name' => 'MacBook Pro M4 16"', 'type' => 'pc'],
                ['name' => 'iMac M4 24"', 'type' => 'pc'],
            ],
            'Xiaomi' => [
                ['name' => 'Xiaomi 15', 'type' => 'celular'],
                ['name' => 'Xiaomi 14T', 'type' => 'celular'],
                ['name' => 'Redmi Note 14', 'type' => 'celular'],
                ['name' => 'Redmi Note 14 Pro', 'type' => 'celular'],
                ['name' => 'Redmi Note 13 Pro', 'type' => 'celular'],
                ['name' => 'Redmi 13', 'type' => 'celular'],
                ['name' => 'Redmi 12', 'type' => 'celular'],
                ['name' => 'Pad 7 Pro', 'type' => 'tablet'],
                ['name' => 'Pad 6', 'type' => 'tablet'],
                ['name' => 'Redmi Pad SE', 'type' => 'tablet'],
                ['name' => 'Mi Notebook Pro', 'type' => 'pc'],
                ['name' => 'RedmiBook 16', 'type' => 'pc'],
                ['name' => 'TV A Pro 55"', 'type' => 'televisor'],
                ['name' => 'TV A Pro 65"', 'type' => 'televisor'],
                ['name' => 'TV A 43"', 'type' => 'televisor'],
            ],
            'Redmi' => [
                ['name' => 'Redmi Note 14', 'type' => 'celular'],
                ['name' => 'Redmi Note 13', 'type' => 'celular'],
                ['name' => 'Redmi 13C', 'type' => 'celular'],
                ['name' => 'Redmi 12', 'type' => 'celular'],
                ['name' => 'Redmi 10C', 'type' => 'celular'],
                ['name' => 'Redmi A3', 'type' => 'celular'],
            ],
            'POCO' => [
                ['name' => 'POCO F7 Pro', 'type' => 'celular'],
                ['name' => 'POCO F6', 'type' => 'celular'],
                ['name' => 'POCO X7 Pro', 'type' => 'celular'],
                ['name' => 'POCO X6', 'type' => 'celular'],
                ['name' => 'POCO M6 Pro', 'type' => 'celular'],
                ['name' => 'POCO C75', 'type' => 'celular'],
            ],
            'Huawei' => [
                ['name' => 'Pura 70 Ultra', 'type' => 'celular'],
                ['name' => 'P60 Pro', 'type' => 'celular'],
                ['name' => 'Mate 70 Pro', 'type' => 'celular'],
                ['name' => 'Nova 12i', 'type' => 'celular'],
                ['name' => 'Nova 12s', 'type' => 'celular'],
                ['name' => 'MatePad 11.5"', 'type' => 'tablet'],
                ['name' => 'MatePad SE 11"', 'type' => 'tablet'],
                ['name' => 'MatePad T', 'type' => 'tablet'],
                ['name' => 'MateBook D16', 'type' => 'pc'],
                ['name' => 'MateBook X Pro', 'type' => 'pc'],
            ],
            'Honor' => [
                ['name' => 'Magic7 Pro', 'type' => 'celular'],
                ['name' => 'Honor 200 Pro', 'type' => 'celular'],
                ['name' => 'Honor 90', 'type' => 'celular'],
                ['name' => 'Honor X9c', 'type' => 'celular'],
                ['name' => 'Honor X8b', 'type' => 'celular'],
                ['name' => 'Pad 9', 'type' => 'tablet'],
                ['name' => 'Pad X9', 'type' => 'tablet'],
                ['name' => 'MagicBook 14 Art', 'type' => 'pc'],
                ['name' => 'MagicBook 16', 'type' => 'pc'],
            ],
            'LG' => [
                ['name' => 'TV OLED C4 55"', 'type' => 'televisor'],
                ['name' => 'TV OLED C4 65"', 'type' => 'televisor'],
                ['name' => 'TV QNED 75"', 'type' => 'televisor'],
                ['name' => 'TV NanoCell 50"', 'type' => 'televisor'],
                ['name' => 'Lavadora TurboWash 14kg', 'type' => 'lavador'],
                ['name' => 'Lavadora TurboWash 17kg', 'type' => 'lavador'],
                ['name' => 'Secadora 9kg', 'type' => 'lavador'],
                ['name' => 'Split Dual Inverter 12000BTU', 'type' => 'aire_split'],
                ['name' => 'Split Dual Inverter 24000BTU', 'type' => 'aire_split'],
                ['name' => 'Gram 14"', 'type' => 'pc'],
                ['name' => 'Gram 16"', 'type' => 'pc'],
                ['name' => 'LG K52', 'type' => 'celular'],
                ['name' => 'LG K62', 'type' => 'celular'],
            ],
            'Nokia' => [
                ['name' => 'Nokia G22', 'type' => 'celular'],
                ['name' => 'Nokia G21', 'type' => 'celular'],
                ['name' => 'Nokia C32', 'type' => 'celular'],
                ['name' => 'Nokia C22', 'type' => 'celular'],
                ['name' => 'Nokia X30', 'type' => 'celular'],
            ],
            'Otras marcas' => [
                ['name' => 'Smartphone Generico', 'type' => 'celular'],
                ['name' => 'Tablet Generica', 'type' => 'tablet'],
                ['name' => 'PC / Laptop Generica', 'type' => 'pc'],
                ['name' => 'Televisor Generico', 'type' => 'televisor'],
                ['name' => 'Aire Acondicionado Generico', 'type' => 'aire_split'],
                ['name' => 'Lavadora Generica', 'type' => 'lavador'],
                ['name' => 'Otro Dispositivo', 'type' => 'otro'],
            ],
        ];

        $brandIds = DeviceBrand::pluck('id', 'name');

        foreach ($modelsByBrand as $brandName => $models) {
            if (! isset($brandIds[$brandName])) {
                continue;
            }
            foreach ($models as $m) {
                DeviceModel::firstOrCreate(
                    ['brand_id' => $brandIds[$brandName], 'name' => $m['name']],
                    ['device_type' => $m['type'], 'is_active' => true],
                );
            }
        }
    }
}
