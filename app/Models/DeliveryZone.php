<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'delivery_fee', 'coverage', 'active'];

    protected $casts = [
        'delivery_fee' => 'decimal:2',
        'active' => 'boolean',
    ];
}
