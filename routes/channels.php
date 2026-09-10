<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal privado por tenant: solo los usuarios del tenant reciben sus eventos.
Broadcast::channel('tenant.{tenantId}', function ($user, int $tenantId) {
    return $user->tenants()->whereKey($tenantId)->exists();
});
