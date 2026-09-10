<?php
namespace Tests\Feature;
use App\Filament\Pages\Inventario;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
class InventarioRenderTest extends TestCase {
    use RefreshDatabase;
    public function test_render(): void {
        $t = Tenant::create(['name'=>'H2O','slug'=>'h2o','rubro'=>'agua']);
        $t->branches()->create(['name'=>'Central','active'=>true]);
        $u = User::factory()->create();
        $t->users()->attach($u->id, ['role'=>'owner']);
        $this->actingAs($u);
        Filament::setTenant($t, isQuiet: true);
        Product::create(['name'=>'Bidón 20L','price'=>25,'unit'=>'bidón']);
        Livewire::test(Inventario::class)->assertOk();
    }
}
