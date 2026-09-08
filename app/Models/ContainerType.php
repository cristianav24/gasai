<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ContainerType extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'active'];

    protected $casts = ['active' => 'boolean'];
}
