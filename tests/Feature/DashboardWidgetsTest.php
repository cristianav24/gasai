<?php

namespace Tests\Feature;

use App\Filament\Widgets\EstadisticasNegocio;
use App\Filament\Widgets\VentasChart;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua']);
        $this->branch = $this->tenant->branches()->create(['name' => 'Central', 'active' => true]);
        $owner = User::factory()->create();
        $this->tenant->users()->attach($owner->id, ['role' => 'owner']);
        $this->actingAs($owner);
        Filament::setTenant($this->tenant, isQuiet: true);
    }

    public function test_los_widgets_del_escritorio_renderizan(): void
    {
        Customer::create(['phone' => '+51987654321', 'name' => 'Juan']);
        Order::create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
            'status' => 'pendiente', 'channel' => 'whatsapp', 'total' => 25,
        ]);
        Sale::create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
            'status' => 'cobrada', 'subtotal' => 30, 'discount_total' => 0, 'total' => 30, 'paid_at' => now(),
        ]);

        Livewire::test(EstadisticasNegocio::class)->assertOk();
        Livewire::test(VentasChart::class)->assertOk();
    }
}
