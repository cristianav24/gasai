<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'phone', 'wa_user_id', 'username', 'name', 'notes'];

    /**
     * Nombre para mostrar: nombre guardado, si no el @username, si no el teléfono.
     * Nunca mostramos el wa_user_id (BSUID técnico de WhatsApp): no le dice nada
     * al operador. Si no hay nada legible, "Cliente".
     */
    public function displayName(): string
    {
        return $this->name
            ?: ($this->username ? '@' . ltrim($this->username, '@') : null)
            ?: $this->phone
            ?: 'Cliente';
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function containerBalances(): HasMany
    {
        return $this->hasMany(ContainerBalance::class);
    }

    public function primaryAddress(): ?Address
    {
        return $this->addresses()->where('is_primary', true)->first()
            ?? $this->addresses()->latest()->first();
    }
}
