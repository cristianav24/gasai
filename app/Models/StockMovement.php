<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'branch_id', 'product_id', 'user_id',
        'type', 'quantity', 'reference',
    ];

    protected $casts = ['quantity' => 'integer'];
}
