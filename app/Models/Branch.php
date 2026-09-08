<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'address', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
