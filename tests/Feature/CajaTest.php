<?php

namespace Tests\Feature;

use App\Filament\Pages\Caja;
use App\Models\Branch;
use App\Models\CashSession;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CajaTest extends TestCase
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

    public function test_abrir_caja(): void
    {
        Livewire::test(Caja::class)->call('abrir', 100.0);

        $session = CashSession::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('abierta', $session->status);
        $this->assertSame('100.00', $session->opening_amount);
    }

    public function test_no_abre_dos_cajas_en_la_misma_sucursal(): void
    {
        Livewire::test(Caja::class)->call('abrir', 100.0);
        Livewire::test(Caja::class)->call('abrir', 50.0);

        $this->assertSame(1, CashSession::withoutGlobalScopes()->count());
    }

    public function test_movimientos_entrada_y_salida(): void
    {
        Livewire::test(Caja::class)->call('abrir', 100.0);
        Livewire::test(Caja::class)->call('movimiento', 'entrada', 30.0, 'Fondo extra');
        Livewire::test(Caja::class)->call('movimiento', 'salida', 20.0, 'Compra de bolsas');

        $session = CashSession::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(10.0, $session->movementsTotal()); // 30 - 20
    }

    public function test_arqueo_incluye_ventas_en_efectivo(): void
    {
        Livewire::test(Caja::class)->call('abrir', 100.0);
        $session = CashSession::withoutGlobalScopes()->firstOrFail();
        $efectivo = PaymentMethod::create(['name' => 'Efectivo', 'is_cash' => true]);

        // Venta en efectivo vinculada al turno.
        Sale::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'payment_method_id' => $efectivo->id,
            'cash_session_id' => $session->id,
            'status' => 'cobrada',
            'total' => 44,
            'paid_at' => now(),
        ]);

        // Esperado = fondo 100 + ventas efectivo 44 = 144.
        $this->assertSame(144.0, $session->fresh()->expectedCash());
    }

    public function test_cerrar_caja_calcula_la_diferencia(): void
    {
        Livewire::test(Caja::class)->call('abrir', 100.0);
        Livewire::test(Caja::class)->call('movimiento', 'entrada', 50.0, 'Ingreso');

        // Esperado = 150. Contamos 148 → faltan 2.
        Livewire::test(Caja::class)->call('cerrar', 148.0, 'Cierre de turno');

        $session = CashSession::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('cerrada', $session->status);
        $this->assertSame('150.00', $session->expected_amount);
        $this->assertSame('148.00', $session->closing_amount);
        $this->assertSame('-2.00', $session->difference);
        $this->assertNotNull($session->closed_at);
    }

    public function test_la_caja_es_aislada_por_tenant(): void
    {
        Livewire::test(Caja::class)->call('abrir', 100.0);

        $otro = Tenant::create(['name' => 'Otro', 'slug' => 'otro', 'rubro' => 'gas']);
        Filament::setTenant($otro, isQuiet: true);

        $this->assertSame(0, CashSession::count());
        $this->assertNull(Livewire::test(Caja::class)->instance()->current());
    }
}
