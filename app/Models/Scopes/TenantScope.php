<?php

namespace App\Models\Scopes;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope obligatorio: toda consulta a un modelo de negocio se filtra por
 * el tenant activo. Un tenant nunca puede leer datos de otro.
 *
 * Si no hay tenant en contexto (ej. jobs de sistema), no filtra; en esos casos
 * la responsabilidad de acotar por tenant es de quien ejecuta el job.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        /** @var class-string<BelongsToTenant> $class */
        $class = $model::class;

        if ($tenantId = $class::currentTenantId()) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
        }
    }
}
