<?php

namespace App\Services\Tenancy;

use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Da de alta un negocio (tenant) nuevo con lo mínimo para operar: sucursal por
 * defecto, métodos de pago típicos en Perú y el usuario como dueño.
 */
class TenantProvisioner
{
    private const METODOS = ['Efectivo', 'Yape', 'Plin', 'Transferencia', 'Crédito'];

    /**
     * @param  array{name: string, rubro?: string}  $data
     */
    public function create(array $data, User $owner): Tenant
    {
        $rubro = $data['rubro'] ?? 'agua';

        return DB::transaction(function () use ($data, $owner, $rubro): Tenant {
            $tenant = Tenant::create([
                'name' => trim($data['name']),
                'slug' => $this->uniqueSlug($data['name']),
                'rubro' => $rubro,
                // El gas suele manejar envases en garantía; el agua normalmente no.
                'tracks_containers' => $rubro === 'gas',
                'currency' => 'PEN',
                'timezone' => 'America/Lima',
            ]);

            // El usuario que lo crea queda como dueño.
            $tenant->users()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);

            // Sucursal por defecto.
            Branch::create(['tenant_id' => $tenant->id, 'name' => 'Central', 'active' => true]);

            // Métodos de pago iniciales.
            foreach (self::METODOS as $metodo) {
                PaymentMethod::create([
                    'tenant_id' => $tenant->id,
                    'name' => $metodo,
                    'active' => true,
                    'is_cash' => $metodo === 'Efectivo',
                ]);
            }

            return $tenant;
        });
    }

    /** Slug único a partir del nombre del negocio. */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'negocio';
        $slug = $base;
        $i = 2;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
