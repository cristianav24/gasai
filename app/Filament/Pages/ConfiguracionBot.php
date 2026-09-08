<?php

namespace App\Filament\Pages;

use App\Models\BotConfig;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Configuración del agente (bot_configs): un único registro por tenant.
 * No es un CRUD; es una página de ajustes que carga y guarda ese registro.
 */
class ConfiguracionBot extends Page
{
    protected string $view = 'filament.pages.configuracion-bot';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $title = 'Configuración del Bot';

    protected static ?string $navigationLabel = 'Configuración del Bot';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración del negocio';

    protected static ?int $navigationSort = 4;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $record = $this->getRecord();

        $this->form->fill($record->exists
            ? $record->attributesToArray()
            : [
                'agent_name' => 'Asistente',
                'tone' => 'amable',
                'temperature' => 0.30,
            ]);
    }

    protected function getRecord(): BotConfig
    {
        // El global scope ya acota por tenant; pasamos tenant_id explícito para
        // que un registro nuevo nazca con el dueño correcto.
        return BotConfig::firstOrNew([
            'tenant_id' => Filament::getTenant()->getKey(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('agent_name')
                    ->label('Nombre del agente')
                    ->helperText('Cómo se presenta el bot al cliente.')
                    ->required()
                    ->maxLength(100),

                Select::make('tone')
                    ->label('Tono')
                    ->options([
                        'amable' => 'Amable y cercano',
                        'formal' => 'Formal',
                        'breve' => 'Breve y directo',
                    ])
                    ->required(),

                TextInput::make('temperature')
                    ->label('Temperatura')
                    ->helperText('0 = respuestas predecibles, 1 = más creativas. Recomendado 0.2–0.4.')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(1)
                    ->step(0.05)
                    ->required(),

                Textarea::make('welcome_message')
                    ->label('Mensaje de bienvenida')
                    ->helperText('Lo primero que ve el cliente al escribir.')
                    ->rows(2)
                    ->columnSpanFull(),

                Textarea::make('extra_instructions')
                    ->label('Instrucciones extra')
                    ->helperText('Reglas puntuales para el agente (ej. "no prometas horas exactas de entrega").')
                    ->rows(4)
                    ->columnSpanFull(),
            ])
            ->statePath('data')
            ->model($this->getRecord());
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $record = $this->getRecord();
        $record->fill($data);
        $record->save();

        Notification::make()
            ->success()
            ->title('Configuración guardada')
            ->send();
    }
}
