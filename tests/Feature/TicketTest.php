<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Branch $branch;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'H2O Wanka', 'slug' => 'h2o', 'rubro' => 'agua']);
        $this->branch = $this->tenant->branches()->create(['name' => 'Central', 'active' => true]);
        $this->owner = User::factory()->create();
        $this->tenant->users()->attach($this->owner->id, ['role' => 'owner']);
        Filament::setTenant($this->tenant, isQuiet: true);
    }

    private function sale(): Sale
    {
        $metodo = PaymentMethod::create(['name' => 'Efectivo']);
        $sale = Sale::create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
            'payment_method_id' => $metodo->id, 'status' => 'cobrada',
            'subtotal' => 30, 'discount_total' => 0, 'total' => 30, 'paid_at' => now(),
        ]);
        $sale->items()->create([
            'tenant_id' => $this->tenant->id, 'product_name' => 'Bidón 20L',
            'quantity' => 1, 'unit_price_list' => 30, 'unit_price_charged' => 30,
        ]);

        return $sale;
    }

    public function test_el_dueno_ve_el_ticket_de_su_venta(): void
    {
        $sale = $this->sale();

        $this->actingAs($this->owner)
            ->get(route('ticket.sale', $sale))
            ->assertOk()
            ->assertSee('H2O Wanka')
            ->assertSee('Bidón 20L')
            ->assertSee('TOTAL');
    }

    public function test_un_usuario_de_otro_tenant_no_ve_el_ticket(): void
    {
        $sale = $this->sale();

        $ajeno = User::factory()->create();
        $otro = Tenant::create(['name' => 'Otro', 'slug' => 'otro', 'rubro' => 'gas']);
        $otro->users()->attach($ajeno->id, ['role' => 'owner']);

        $this->actingAs($ajeno)
            ->get(route('ticket.sale', $sale))
            ->assertForbidden();
    }

    public function test_sin_sesion_no_ve_el_ticket(): void
    {
        $sale = $this->sale();

        $this->get(route('ticket.sale', $sale))->assertForbidden();
    }
}
