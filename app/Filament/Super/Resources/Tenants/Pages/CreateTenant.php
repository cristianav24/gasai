<?php

namespace App\Filament\Super\Resources\Tenants\Pages;

use App\Filament\Super\Resources\Tenants\TenantResource;
use App\Models\User;
use App\Services\Tenancy\TenantProvisioner;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * Aprovisiona el negocio y su dueño en un solo paso. Los campos owner_* no se
     * guardan en el modelo (dehydrated:false), se leen del estado del formulario.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $email = trim((string) ($this->data['owner_email'] ?? ''));
        $name = trim((string) ($this->data['owner_name'] ?? ''));
        $password = (string) ($this->data['owner_password'] ?? '');

        $owner = User::where('email', $email)->first();

        if (! $owner) {
            $owner = User::create([
                'name' => $name ?: $email,
                'email' => $email,
                'password' => Hash::make($password),
            ]);
        }

        $tenant = app(TenantProvisioner::class)->create([
            'name' => $data['name'],
            'rubro' => $data['rubro'],
            'tracks_containers' => $data['tracks_containers'] ?? null,
        ], $owner);

        Notification::make()->success()
            ->title('Negocio creado')
            ->body("“{$tenant->name}” quedó listo con {$owner->email} como dueño.")
            ->send();

        return $tenant;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
