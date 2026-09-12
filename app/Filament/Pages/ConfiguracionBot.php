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
use Filament\Schemas\Components\Section;
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

        $base = $record->exists
            ? $record->attributesToArray()
            : ['agent_name' => 'Asistente', 'tone' => 'amable', 'temperature' => 0.30];

        $tenant = Filament::getTenant();
        $this->form->fill($base + [
            'geo_city' => $tenant->geo_city,
            'geo_region' => $tenant->geo_region,
            'geo_country' => $tenant->geo_country ?: 'Perú',
            'geo_viewbox' => $tenant->geo_viewbox,
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

                Section::make('Zona del negocio')
                    ->description('Ayuda al asistente a ubicar con exactitud las direcciones que te escriben tus clientes.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('geo_city')->label('Ciudad')->placeholder('Ej: Huancayo')->maxLength(120)->dehydrated(false),
                        TextInput::make('geo_region')->label('Región / Departamento')->placeholder('Ej: Junín')->maxLength(120)->dehydrated(false),
                        TextInput::make('geo_country')->label('País')->default('Perú')->maxLength(120)->dehydrated(false),
                        TextInput::make('geo_viewbox')->label('Recuadro (avanzado)')
                            ->helperText('Opcional: "lon1,lat1,lon2,lat2" para acotar la búsqueda a tu ciudad.')
                            ->maxLength(120)->dehydrated(false),
                    ]),
            ])
            ->statePath('data')
            ->model($this->getRecord());
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // La zona del negocio vive en el tenant, no en la config del bot.
        $state = $this->data;
        Filament::getTenant()->update([
            'geo_city' => filled($state['geo_city'] ?? null) ? trim($state['geo_city']) : null,
            'geo_region' => filled($state['geo_region'] ?? null) ? trim($state['geo_region']) : null,
            'geo_country' => filled($state['geo_country'] ?? null) ? trim($state['geo_country']) : null,
            'geo_viewbox' => filled($state['geo_viewbox'] ?? null) ? trim($state['geo_viewbox']) : null,
        ]);

        $record = $this->getRecord();
        $record->fill($data);
        $record->save();

        Notification::make()
            ->success()
            ->title('Configuración guardada')
            ->send();
    }
}
