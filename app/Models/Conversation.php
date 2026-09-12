<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'customer_id', 'phone', 'wa_user_id', 'contact_name', 'channel',
        'status', 'assigned_user_id', 'last_activity_at', 'last_inbound_at',
    ];

    /** Etiqueta del contacto: nombre del cliente, nombre de WhatsApp, o teléfono/ID. */
    public function contactLabel(): string
    {
        return $this->customer?->displayName()
            ?: $this->contact_name
            ?: $this->phone
            ?: $this->wa_user_id
            ?: 'Anónimo';
    }

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

    /** Último mensaje del hilo (para la vista previa en la bandeja). */
    public function lastMessage(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
