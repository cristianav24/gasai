<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceToken extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'user_id', 'token', 'platform'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
