<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContainerBalance extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'customer_id', 'container_type_id', 'balance'];

    protected $casts = ['balance' => 'integer'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function containerType(): BelongsTo
    {
        return $this->belongsTo(ContainerType::class);
    }
}
