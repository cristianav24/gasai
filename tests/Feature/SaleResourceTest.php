<?php

namespace Tests\Feature;

use App\Filament\Resources\Sales\Pages\ListSales;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SaleResourceTest extends TestCase
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

    private function sale(array $attrs = []): Sale
    {
        return Sale::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'status' => 'cobrada',
            'subtotal' => 25,
            'discount_total' => 0,
            'total' => 25,
            'paid_at' => now(),
        ], $attrs));
    }

    public function test_lista_muestra_las_ventas_del_tenant(): void
    {
        $mia = $this->sale(['total' => 30]);

        Livewire::test(ListSales::class)
            ->assertCanSeeTableRecords([$mia]);
    }

    public function test_no_muestra_ventas_de_otro_tenant(): void
    {
        $mia = $this->sale();

        $otro = Tenant::create(['name' => 'Otro', 'slug' => 'otro', 'rubro' => 'gas']);
        $otroBranch = $otro->branches()->create(['name' => 'Central', 'active' => true]);
        $ajena = Sale::withoutGlobalScopes()->create([
            'tenant_id' => $otro->id, 'branch_id' => $otroBranch->id, 'status' => 'cobrada',
            'subtotal' => 10, 'discount_total' => 0, 'total' => 10, 'paid_at' => now(),
        ]);

        Livewire::test(ListSales::class)
            ->assertCanSeeTableRecords([$mia])
            ->assertCanNotSeeTableRecords([$ajena]);
    }

    public function test_filtra_por_estado(): void
    {
        $cobrada = $this->sale(['status' => 'cobrada']);
        $espera = $this->sale(['status' => 'en_espera', 'paid_at' => null]);

        Livewire::test(ListSales::class)
            ->filterTable('status', 'en_espera')
            ->assertCanSeeTableRecords([$espera])
            ->assertCanNotSeeTableRecords([$cobrada]);
    }
}
