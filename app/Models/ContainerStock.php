<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inventario físico de envases del negocio por tipo: llenos, vacíos y nuevos.
 */
class ContainerStock extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'container_type_id', 'full_count', 'empty_count', 'new_count'];

    protected $casts = [
        'full_count' => 'integer',
        'empty_count' => 'integer',
        'new_count' => 'integer',
    ];

    public function containerType(): BelongsTo
    {
        return $this->belongsTo(ContainerType::class);
    }
}
