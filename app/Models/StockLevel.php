<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'branch_id', 'product_id', 'quantity'];

    protected $casts = ['quantity' => 'integer'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
