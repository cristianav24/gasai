<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ContainerMovement extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'customer_id', 'container_type_id', 'user_id',
        'type', 'delta', 'reference',
    ];

    protected $casts = ['delta' => 'integer'];
}
