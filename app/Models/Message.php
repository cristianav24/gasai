<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'conversation_id', 'role', 'content',
        'wa_message_id', 'raw_payload', 'processed_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
