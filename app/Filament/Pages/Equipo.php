<?php

namespace App\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;

/**
 * Gestión del equipo del tenant: dueños, operadores y repartidores. Permite al
 * dueño dar de alta repartidores sin tocar la base de datos.
 */
class Equipo extends Page
{
    protected string $view = 'filament.pages.equipo';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $title = 'Equipo';

    protected static ?string $navigationLabel = 'Equipo';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración del negocio';

    protected static ?int $navigationSort = 7;

    public const ROLES = [
        'owner' => 'Dueño',
        'operator' => 'Operador',
        'courier' => 'Repartidor',
    ];

    /** @return array<int, array{id: int, name: string, email: string, role: string}> */
    public function members(): array
    {
        return Filament::getTenant()
            ->users()
            ->orderBy('name')
            ->get()
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->pivot->role,
            ])
            ->all();
    }

    public function roleLabel(string $role): string
    {
        return self::ROLES[$role] ?? $role;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('agregar')
                ->label('Agregar miembro')
                ->icon(Heroicon::OutlinedUserPlus)
                ->form([
                    TextInput::make('name')->label('Nombre')->required(),
                    TextInput::make('email')->label('Correo')->email()->required(),
                    Select::make('role')->label('Rol')->options(self::ROLES)->default('courier')->required(),
                    TextInput::make('password')
                        ->label('Contraseña')
                        ->password()
                        ->revealable()
                        ->helperText('Solo se usa si el correo es nuevo.')
                        ->minLength(8),
                ])
                ->action(function (array $data): void {
                    $existing = User::where('email', $data['email'])->first();

                    if (! $existing && blank($data['password'] ?? null)) {
                        Notification::make()->danger()
                            ->title('Falta la contraseña para el usuario nuevo')->send();

                        return;
                    }

                    $user = $existing ?? User::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'password' => Hash::make($data['password']),
                    ]);

                    Filament::getTenant()->users()->syncWithoutDetaching([
                        $user->id => ['role' => $data['role']],
                    ]);

                    Notification::make()->success()->title('Miembro agregado')->send();
                }),
        ];
    }

    public function changeRole(int $userId, string $role): void
    {
        if (! array_key_exists($role, self::ROLES)) {
            return;
        }

        Filament::getTenant()->users()->updateExistingPivot($userId, ['role' => $role]);

        Notification::make()->success()->title('Rol actualizado')->send();
    }

    public function removeMember(int $userId): void
    {
        // No permitir quedarse sin dueños.
        $tenant = Filament::getTenant();
        $owners = $tenant->users()->wherePivot('role', 'owner')->count();
        $target = $tenant->users()->whereKey($userId)->first();

        if ($target && $target->pivot->role === 'owner' && $owners <= 1) {
            Notification::make()->danger()->title('No puedes quitar al único dueño')->send();

            return;
        }

        $tenant->users()->detach($userId);

        Notification::make()->success()->title('Miembro removido')->send();
    }
}
