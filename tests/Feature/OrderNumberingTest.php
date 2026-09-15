<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderNumberingTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(Tenant $t, Branch $b): Order
    {
        return Order::withoutGlobalScopes()->create([
            'tenant_id' => $t->id, 'branch_id' => $b->id,
            'status' => 'pendiente', 'channel' => 'manual', 'total' => 10,
        ]);
    }

    public function test_el_numero_empieza_en_el_configurado_y_se_formatea(): void
    {
        $t = Tenant::create([
            'name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua',
            'order_number_start' => 1000, 'order_number_padding' => 8,
        ]);
        $b = $t->branches()->create(['name' => 'Central', 'active' => true]);

        $o1 = $this->makeOrder($t, $b);
        $o2 = $this->makeOrder($t, $b);

        $this->assertSame(1000, $o1->number);
        $this->assertSame(1001, $o2->number);
        $this->assertSame('00001000', $o1->displayNumber());
        $this->assertSame('00001001', $o2->displayNumber());
    }

    public function test_la_numeracion_es_independiente_por_negocio(): void
    {
        $a = Tenant::create(['name' => 'A', 'slug' => 'a', 'rubro' => 'agua', 'order_number_start' => 500, 'order_number_padding' => 4]);
        $ab = $a->branches()->create(['name' => 'C', 'active' => true]);
        $b = Tenant::create(['name' => 'B', 'slug' => 'b', 'rubro' => 'gas', 'order_number_start' => 1, 'order_number_padding' => 5]);
        $bb = $b->branches()->create(['name' => 'C', 'active' => true]);

        $this->assertSame(500, $this->makeOrder($a, $ab)->number);
        $this->assertSame(1, $this->makeOrder($b, $bb)->number);
        $this->assertSame(501, $this->makeOrder($a, $ab)->number);
    }
}
