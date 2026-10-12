<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashClosing;
use App\Models\Sale;
use App\Models\SparePart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use RefreshDatabase;

    private function part(string $name, int $stock, float $price): SparePart
    {
        $part = SparePart::create(['name' => $name, 'code' => 'P-' . uniqid(), 'category' => 'Accesorios', 'cost_price' => $price / 2, 'sale_price' => $price, 'min_stock' => 0]);
        $part->setBranchStock(Branch::mainId(), $stock);

        return $part;
    }

    public function test_receptionist_sells_and_stock_and_cash_follow(): void
    {
        $desk = User::factory()->role('receptionist')->create();
        $case = $this->part('Funda A15', 10, 8);
        $charger = $this->part('Cargador 20W', 3, 15);

        $this->actingAs($desk)->post(route('sales.store'), [
            'items' => [
                ['spare_part_id' => $case->id, 'quantity' => 2],
                ['spare_part_id' => $charger->id, 'quantity' => 1],
            ],
            'customer_name' => 'Cliente de paso',
            'method' => 'cash',
        ])->assertSessionHas('success');

        $sale = Sale::first();
        $this->assertEquals(31, $sale->total);
        $this->assertStringStartsWith('VTA-', $sale->sale_number);
        $this->assertSame(8, $case->fresh()->stock_quantity);
        $this->assertSame(2, $charger->fresh()->stock_quantity);
        $this->assertSame(2, $case->movements()->where('sale_id', $sale->id)->value('quantity'));

        $this->actingAs($desk)->get(route('cash.index'))
            ->assertOk()
            ->assertSee($sale->sale_number)
            ->assertViewHas('totals', fn ($t) => $t['cash'] == 31);

        $this->actingAs($desk)->get(route('sales.receipt', $sale))->assertOk()->assertSee('Funda A15');
        $this->actingAs($desk)->get(route('sales.index'))->assertOk()->assertSee('Cliente de paso');
    }

    public function test_sale_without_stock_sells_nothing(): void
    {
        $desk = User::factory()->role('receptionist')->create();
        $case = $this->part('Funda', 10, 8);
        $charger = $this->part('Cargador', 1, 15);

        $this->actingAs($desk)->post(route('sales.store'), [
            'items' => [['spare_part_id' => $case->id, 'quantity' => 1], ['spare_part_id' => $charger->id, 'quantity' => 2]],
            'method' => 'cash',
        ])->assertSessionHas('error');

        $this->assertSame(0, Sale::count());
        $this->assertSame(10, $case->fresh()->stock_quantity);
    }

    public function test_admin_cancels_sale_and_stock_returns(): void
    {
        $admin = User::factory()->role('admin')->create();
        $desk = User::factory()->role('receptionist')->create();
        $case = $this->part('Funda', 5, 8);
        $this->actingAs($desk)->post(route('sales.store'), ['items' => [['spare_part_id' => $case->id, 'quantity' => 2]], 'method' => 'cash']);
        $sale = Sale::first();

        $this->actingAs($desk)->patch(route('sales.cancel', $sale))->assertForbidden();
        $this->actingAs($admin)->patch(route('sales.cancel', $sale))->assertSessionHas('success');

        $this->assertTrue($sale->fresh()->isCancelled());
        $this->assertSame(5, $case->fresh()->stock_quantity);
        $this->actingAs($admin)->get(route('cash.index'))->assertViewHas('totals', fn ($t) => $t['total'] == 0);
        $this->actingAs($admin)->patch(route('sales.cancel', $sale))->assertSessionHas('error');
    }

    public function test_sale_of_closed_day_cannot_be_cancelled(): void
    {
        $admin = User::factory()->role('admin')->create();
        $case = $this->part('Funda', 5, 8);
        $this->actingAs($admin)->post(route('sales.store'), ['items' => [['spare_part_id' => $case->id, 'quantity' => 1]], 'method' => 'cash']);
        $sale = Sale::first();
        $sale->update(['sold_at' => now()->subDay()]);
        CashClosing::create(['branch_id' => Branch::mainId(), 'closing_date' => today()->subDay(), 'expected_cash' => 8, 'counted_cash' => 8, 'difference' => 0]);

        $this->actingAs($admin)->patch(route('sales.cancel', $sale))->assertSessionHas('error');
        $this->assertFalse($sale->fresh()->isCancelled());
    }

    public function test_sales_count_as_revenue_in_reports(): void
    {
        $admin = User::factory()->role('admin')->create();
        $case = $this->part('Funda', 5, 8);
        $this->actingAs($admin)->post(route('sales.store'), ['items' => [['spare_part_id' => $case->id, 'quantity' => 2]], 'method' => 'transfer']);

        $this->actingAs($admin)->get(route('reports.index'))
            ->assertViewHas('kpis', fn ($k) => $k['revenue'] == 16 && $k['sales'] == 16)
            ->assertViewHas('monthly', fn ($m) => $m->last()['amount'] == 16);
    }

    public function test_technicians_cannot_sell_and_other_branch_cannot_see(): void
    {
        $this->actingAs(User::factory()->role('technician')->create())->get(route('sales.create'))->assertForbidden();

        $admin = User::factory()->role('admin')->create();
        $case = $this->part('Funda', 5, 8);
        $this->actingAs($admin)->post(route('sales.store'), ['items' => [['spare_part_id' => $case->id, 'quantity' => 1]], 'method' => 'cash']);

        $other = Branch::create(['name' => 'Quito', 'code' => 'QTO', 'is_active' => true]);
        $qtoDesk = User::factory()->role('receptionist')->create(['branch_id' => $other->id]);
        $this->actingAs($qtoDesk)->get(route('sales.show', Sale::first()))->assertForbidden();
    }
}
