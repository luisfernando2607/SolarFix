<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Order;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // --- Clients ---
        $clientsData = [
            ['name' => 'Carlos Mendoza', 'id_document' => '1712345678', 'phone' => '0987654321', 'email' => 'carlos.m@email.com'],
            ['name' => 'María Fernández', 'id_document' => '1723456789', 'phone' => '0998765432', 'email' => 'maria.f@email.com'],
            ['name' => 'Pedro Gómez', 'id_document' => '1734567890', 'phone' => '0976543210', 'email' => 'pedro.g@email.com'],
            ['name' => 'Ana Castillo', 'id_document' => '1745678901', 'phone' => '0965432109', 'email' => 'ana.c@email.com'],
            ['name' => 'Luis Vera', 'id_document' => '1756789012', 'phone' => '0954321098', 'email' => 'luis.v@email.com'],
            ['name' => 'Gabriela Torres', 'id_document' => '1767890123', 'phone' => '0943210987', 'email' => 'gaby.t@email.com'],
            ['name' => 'Diego Salazar', 'id_document' => '1778901234', 'phone' => '0932109876', 'email' => 'diego.s@email.com'],
            ['name' => 'Sofía Ramírez', 'id_document' => '1789012345', 'phone' => '0921098765', 'email' => 'sofia.r@email.com'],
            ['name' => 'Andrés Quelal', 'id_document' => '1790123456', 'phone' => '0910987654', 'email' => 'andres.q@email.com'],
            ['name' => 'Valentina Paz', 'id_document' => '1701234567', 'phone' => '0909876543', 'email' => 'vale.p@email.com'],
            ['name' => 'Jorge Naranjo', 'id_document' => '1713456789', 'phone' => '0981234567', 'email' => 'jorge.n@email.com'],
            ['name' => 'Daniela Mora', 'id_document' => '1724567890', 'phone' => '0972345678', 'email' => 'dani.m@email.com'],
            ['name' => 'Ricardo Loor', 'id_document' => '1735678901', 'phone' => '0963456789', 'email' => 'ricky.l@email.com'],
            ['name' => 'Camila Jiménez', 'id_document' => '1746789012', 'phone' => '0954567890', 'email' => 'camila.j@email.com'],
            ['name' => 'Felipe Ibarra', 'id_document' => '1757890123', 'phone' => '0945678901', 'email' => 'felipe.i@email.com'],
            ['name' => 'Andrea Herrera', 'id_document' => '1768901234', 'phone' => '0936789012', 'email' => 'andrea.h@email.com'],
            ['name' => 'Tomás Guerra', 'id_document' => '1779012345', 'phone' => '0927890123', 'email' => 'tomas.g@email.com'],
            ['name' => 'Fernanda Delgado', 'id_document' => '1780123456', 'phone' => '0918901234', 'email' => 'fer.d@email.com'],
            ['name' => 'Esteban Cruz', 'id_document' => '1791234567', 'phone' => '0909012345', 'email' => 'esteban.c@email.com'],
            ['name' => 'Patricia Benítez', 'id_document' => '1702345678', 'phone' => '0990123456', 'email' => 'paty.b@email.com'],
            ['name' => 'Gustavo Arias', 'id_document' => '1714567890', 'phone' => '0982345678', 'email' => 'gus.a@email.com'],
            ['name' => 'Natalia Valencia', 'id_document' => '1725678901', 'phone' => '0973456789', 'email' => 'nata.v@email.com'],
            ['name' => 'Manuel Zambrano', 'id_document' => '1736789012', 'phone' => '0964567890', 'email' => 'manu.z@email.com'],
            ['name' => 'Carolina Tello', 'id_document' => '1747890123', 'phone' => '0955678901', 'email' => 'caro.t@email.com'],
            ['name' => 'Sebastián Ulloa', 'id_document' => '1758901234', 'phone' => '0946789012', 'email' => 'sebas.u@email.com'],
        ];

        foreach ($clientsData as $data) {
            Client::create($data);
        }

        // --- Consolidate brands (remove duplicates, keep first occurrence per name) ---
        DB::statement('SET @row := 0');
        // Keep only the first occurrence of each brand name
        $keepIds = [];
        $seen = [];
        $brands = DB::table('device_brands')->orderBy('id')->get();
        foreach ($brands as $b) {
            if (!isset($seen[$b->name])) {
                $seen[$b->name] = $b->id;
                $keepIds[] = $b->id;
            }
        }
        DB::table('device_brands')->whereNotIn('id', $keepIds)->delete();

        // Update device_types JSON for each kept brand
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
            DB::table('device_brands')->where('name', $name)->update([
                'device_types' => json_encode($types),
                'device_type' => 'otro',
            ]);
        }

        // --- Seed device models ---
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

        $brandNameIdMap = DB::table('device_brands')->pluck('id', 'name')->toArray();

        foreach ($modelsByBrand as $brandName => $models) {
            $brandId = $brandNameIdMap[$brandName] ?? null;
            if (!$brandId) continue;
            foreach ($models as $m) {
                DB::table('device_models')->insert([
                    'brand_id' => $brandId,
                    'name' => $m['name'],
                    'device_type' => $m['type'],
                    'is_active' => true,
                ]);
            }
        }

        // --- Create orders ---
        $allClientIds = Client::pluck('id')->toArray();
        $allModels = DB::table('device_models')->get()->toArray();
        $modelByBrand = [];
        foreach ($allModels as $m) {
            $modelByBrand[$m->brand_id][] = $m;
        }

        if (empty($allClientIds) || empty($allModels)) return;

        $statuses = ['received', 'diagnosing', 'waiting_approval', 'repairing', 'ready', 'delivered', 'closed_no_repair'];
        $deviceTypes = ['celular', 'tablet', 'pc', 'televisor', 'aire_split', 'lavador'];
        $unlockTypes = ['none', 'pin', 'pattern', 'unknown'];
        $patterns = ['1-2-5-8', '1-2-3-6-9', '1-4-7-8-9', '2-5-8-7-6', '1-2-3-6-5-4-7-8-9', '4-5-6-9', '3-5-7-9', '2-4-6-8'];

        $now = now();

        for ($i = 1; $i <= 20; $i++) {
            $clientId = $allClientIds[array_rand($allClientIds)];
            $brandId = array_rand($modelByBrand);
            $modelRow = $modelByBrand[$brandId][array_rand($modelByBrand[$brandId])];
            $status = $statuses[array_rand($statuses)];
            $deviceType = $deviceTypes[array_rand($deviceTypes)];
            $unlockType = $unlockTypes[array_rand($unlockTypes)];

            $unlockValue = null;
            if ($unlockType === 'pin') {
                $unlockValue = (string) random_int(1000, 9999);
            } elseif ($unlockType === 'pattern') {
                $unlockValue = $patterns[array_rand($patterns)];
            }

            $entryDate = $now->copy()->subDays(random_int(1, 45));
            $diagnosisCost = random_int(0, 3) * 10;
            $laborCost = random_int(5, 40) * 5;
            $partsCost = random_int(0, 20) * 5;
            $surchargePct = random_int(0, 2) * 5;
            $subtotal = $diagnosisCost + $laborCost + $partsCost;
            $surchargeAmt = $subtotal * $surchargePct / 100;
            $total = $subtotal + $surchargeAmt;
            $amountPaid = in_array($status, ['delivered', 'closed_no_repair']) ? $total : (random_int(0, 1) ? round($total * random_int(3, 10) / 10, 2) : 0);
            $balance = round($total - $amountPaid, 2);

            $orderNumber = 'ORD-' . $entryDate->format('Ymd') . '-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);

            $order = Order::create([
                'branch_id' => 1,
                'client_id' => $clientId,
                'user_id' => 1,
                'created_by' => 1,
                'order_number' => $orderNumber,
                'device_type' => $deviceType,
                'brand_id' => $brandId,
                'model_id' => $modelRow->id,
                'serial_imei' => random_int(100000000000000, 999999999999999),
                'physical_condition' => 'Golpes en esquina superior derecha. Pantalla con rayones leves.',
                'declared_fault' => 'No enciende' . (random_int(0, 1) ? '. Se apaga solo después de unos minutos.' : '. Pantalla no responde al tacto.'),
                'unlock_type' => $unlockType,
                'unlock_value' => $unlockValue,
                'status' => $status,
                'entry_date' => $entryDate->format('Y-m-d'),
                'estimated_delivery' => $entryDate->copy()->addDays(random_int(2, 10))->format('Y-m-d'),
                'delivery_date' => in_array($status, ['delivered', 'closed_no_repair']) ? $now->copy()->subDays(random_int(1, 10))->format('Y-m-d') : null,
                'diagnosis_cost' => $diagnosisCost,
                'labor_cost' => $laborCost,
                'parts_cost' => $partsCost,
                'surcharge_percent' => $surchargePct,
                'surcharge_amount' => $surchargeAmt,
                'total_amount' => $total,
                'amount_paid' => $amountPaid,
                'balance_due' => max(0, $balance),
                'created_at' => $entryDate,
                'updated_at' => $entryDate,
            ]);

            if ($order->amount_paid > 0) {
                $order->payments()->create([
                    'amount' => $order->amount_paid,
                    'method' => 'cash',
                    'reference' => 'Pago inicial',
                    'paid_at' => $entryDate,
                    'registered_by' => 1,
                ]);
            }

            $order->statusHistory()->create([
                'from_status' => null,
                'to_status' => $status,
                'changed_by' => 1,
                'notes' => 'Orden creada desde seeder',
                'changed_at' => $entryDate,
            ]);
        }
    }
}
