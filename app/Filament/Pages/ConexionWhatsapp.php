<?php

namespace App\Filament\Pages;

use App\Models\WhatsappAccount;
use App\Services\WhatsApp\WhatsAppGateway;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Conexión manual con WhatsApp: el dueño pega phone_number_id, waba_id y access
 * token. Un registro por tenant. El Embedded Signup de Meta llega después.
 */
class ConexionWhatsapp extends Page
{
    protected string $view = 'filament.pages.conexion-whatsapp';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleOvalLeftEllipsis;

    protected static ?string $title = 'Conexión de WhatsApp';

    protected static ?string $navigationLabel = 'WhatsApp';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración del negocio';

    protected static ?int $navigationSort = 5;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $account = $this->getRecord();

        $this->form->fill($account->exists ? [
            'phone_number_id' => $account->phone_number_id,
            'waba_id' => $account->waba_id,
            'access_token' => $account->access_token,
        ] : []);
    }

    protected function getRecord(): WhatsappAccount
    {
        return WhatsappAccount::firstOrNew(['tenant_id' => Filament::getTenant()->getKey()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('phone_number_id')
                    ->label('Phone Number ID')
                    ->helperText('Lo encuentras en el panel de WhatsApp de Meta, en tu número.')
                    ->required(),

                TextInput::make('waba_id')
                    ->label('WABA ID')
                    ->helperText('ID de tu cuenta de WhatsApp Business.'),

                TextInput::make('access_token')
                    ->label('Access Token permanente')
                    ->helperText('Se guarda encriptado. Nunca se muestra a terceros.')
                    ->password()
                    ->revealable()
                    ->required(),
            ])
            ->statePath('data')
            ->model($this->getRecord());
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $account = $this->getRecord();
        $account->fill([
            'phone_number_id' => $data['phone_number_id'],
            'waba_id' => $data['waba_id'] ?? null,
            'access_token' => $data['access_token'],
        ]);
        $account->save();

        Notification::make()->success()->title('Conexión guardada')->send();
    }

    public function testConnection(WhatsAppGateway $gateway): void
    {
        $account = $this->getRecord();

        if (! $account->exists) {
            Notification::make()->warning()->title('Primero guarda los datos de conexión')->send();

            return;
        }

        $result = $gateway->verifyCredentials($account);

        if ($result['ok'] ?? false) {
            $account->update(['status' => 'connected']);
            Notification::make()->success()->title('¡Conexión exitosa!')->send();
        } else {
            $account->update(['status' => 'error']);
            Notification::make()
                ->danger()
                ->title('No se pudo conectar')
                ->body($result['error'] ?? 'Revisa tus credenciales.')
                ->send();
        }
    }

    public function webhookUrl(): string
    {
        return url('/webhooks/whatsapp');
    }

    public function verifyToken(): string
    {
        return (string) config('services.whatsapp.verify_token');
    }

    public function connectionStatus(): string
    {
        return $this->getRecord()->status ?? 'disconnected';
    }
}
