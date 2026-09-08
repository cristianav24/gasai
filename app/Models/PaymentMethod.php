<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'active', 'is_cash'];

    protected $casts = ['active' => 'boolean', 'is_cash' => 'boolean'];
}
