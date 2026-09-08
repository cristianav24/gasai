<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BotConfig extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'agent_name', 'tone', 'extra_instructions',
        'welcome_message', 'temperature',
    ];

    protected $casts = ['temperature' => 'decimal:2'];
}
