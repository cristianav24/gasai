<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Aísla un modelo de negocio por tenant.
 *
 * - Aplica el global scope que filtra todas las consultas por tenant_id.
 * - Al crear un registro, asigna tenant_id automáticamente desde el contexto
 *   (tenant de Filament o del usuario autenticado), nunca desde input del cliente.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (! $model->tenant_id && $tenantId = static::currentTenantId()) {
                $model->tenant_id = $tenantId;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Tenant activo: el de Filament si hay panel, si no el primero del usuario.
     */
    public static function currentTenantId(): ?int
    {
        if (function_exists('filament') && filament()->getTenant()) {
            return filament()->getTenant()->getKey();
        }

        return Auth::user()?->tenants()->first()?->getKey();
    }
}
