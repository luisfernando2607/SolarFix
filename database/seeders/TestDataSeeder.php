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
