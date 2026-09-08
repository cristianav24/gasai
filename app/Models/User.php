<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /** Pedidos asignados a este usuario como repartidor. */
    public function assignedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'courier_id');
    }

    // --- Contratos de Filament (tenancy) ---

    public function getTenants(Panel $panel): Collection
    {
        return $this->tenants;
    }

    public function canAccessTenant(\Illuminate\Database\Eloquent\Model $tenant): bool
    {
        return $this->tenants()->whereKey($tenant->getKey())->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Rol del usuario dentro de un tenant dado (owner | operator | courier).
     */
    public function roleIn(Tenant $tenant): ?string
    {
        return $this->tenants()->whereKey($tenant->getKey())->first()?->pivot->role;
    }
}
