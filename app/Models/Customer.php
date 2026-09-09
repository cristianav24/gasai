<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'phone', 'wa_user_id', 'username', 'name', 'notes'];

    /** Nombre para mostrar: nombre guardado, si no el @username, si no el teléfono/ID. */
    public function displayName(): string
    {
        return $this->name
            ?: ($this->username ? '@' . ltrim($this->username, '@') : null)
            ?: $this->phone
            ?: $this->wa_user_id
            ?: 'Anónimo';
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function primaryAddress(): ?Address
    {
        return $this->addresses()->where('is_primary', true)->first()
            ?? $this->addresses()->latest()->first();
    }
}
