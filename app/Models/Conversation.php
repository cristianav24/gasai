<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'customer_id', 'phone', 'channel',
        'status', 'assigned_user_id', 'last_activity_at', 'last_inbound_at',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
        'last_inbound_at' => 'datetime',
    ];

    /** ¿Estamos dentro de la ventana de 24 h de WhatsApp para enviar texto libre? */
    public function within24hWindow(): bool
    {
        return $this->last_inbound_at !== null
            && $this->last_inbound_at->gt(now()->subHours(24));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
