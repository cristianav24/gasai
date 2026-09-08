<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WhatsappAccount extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'phone_number_id', 'waba_id', 'access_token', 'status',
    ];

    protected $casts = [
        'access_token' => 'encrypted',   // Nunca se guarda en claro
    ];
}
