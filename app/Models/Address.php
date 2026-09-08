<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'customer_id', 'address', 'reference',
        'lat', 'lng', 'is_primary',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'is_primary' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
