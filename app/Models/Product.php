<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'description', 'price', 'unit',
        'type', 'requires_empty', 'active', 'container_type_id',
    ];

    public function containerType(): BelongsTo
    {
        return $this->belongsTo(ContainerType::class);
    }

    protected $casts = [
        'price' => 'decimal:2',
        'requires_empty' => 'boolean',
        'active' => 'boolean',
    ];
}
