<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Tenant #1: el propio negocio de agua.
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'h2o-wanka'],
            [
                'name' => 'H2O Wanka',
                'rubro' => 'agua',
                'currency' => 'PEN',
                'timezone' => 'America/Lima',
            ],
        );

        // Sucursal por defecto "Central" (capa distinta del multi-tenant).
        Branch::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Central'],
            ['active' => true],
        );

        // Usuario dueño.
        $owner = User::firstOrCreate(
            ['email' => 'francia24vs@gmail.com'],
            [
                'name' => 'Dueño H2O Wanka',
                'password' => Hash::make('password'),
            ],
        );

        // Vincular dueño al tenant con rol owner.
        $tenant->users()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner'],
        ]);

        // Métodos de pago típicos en Perú.
        foreach (['Efectivo', 'Yape', 'Plin', 'Transferencia', 'Crédito'] as $metodo) {
            \App\Models\PaymentMethod::firstOrCreate([
                'tenant_id' => $tenant->id,
                'name' => $metodo,
            ], ['active' => true, 'is_cash' => $metodo === 'Efectivo']);
        }
    }
}
