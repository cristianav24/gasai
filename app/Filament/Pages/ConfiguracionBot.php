<?php

namespace App\Filament\Pages;

use App\Models\BotConfig;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
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
            'timezone' => $tenant->timezone ?: 'America/Lima',
            'geo_city' => $tenant->geo_city,
            'geo_region' => $tenant->geo_region,
            'geo_country' => $tenant->geo_country ?: 'Perú',
            'geo_viewbox' => $tenant->geo_viewbox,
            'order_number_start' => $tenant->order_number_start ?? 1,
            'order_number_padding' => $tenant->order_number_padding ?? 5,
            'delivery_center' => ($tenant->delivery_center_lat !== null && $tenant->delivery_center_lng !== null)
                ? $tenant->delivery_center_lat . ', ' . $tenant->delivery_center_lng
                : null,
            'delivery_free_over' => $tenant->delivery_free_over,
            'delivery_bands' => collect($tenant->delivery_bands ?? [])
                ->map(fn ($b): array => ['to_km' => $b['to_km'] ?? null, 'fee' => $b['fee'] ?? null])
                ->all(),
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

                Select::make('timezone')
                    ->label('Zona horaria')
                    ->helperText('Las fechas y horas del panel y del bot se muestran en esta zona.')
                    ->options([
                        'America/Lima' => 'Perú (Lima) — UTC-5',
                        'America/Bogota' => 'Colombia (Bogotá) — UTC-5',
                        'America/Guayaquil' => 'Ecuador (Guayaquil) — UTC-5',
                        'America/La_Paz' => 'Bolivia (La Paz) — UTC-4',
                        'America/Santiago' => 'Chile (Santiago)',
                        'America/Mexico_City' => 'México (CDMX) — UTC-6',
                        'America/Argentina/Buenos_Aires' => 'Argentina (Buenos Aires) — UTC-3',
                    ])
                    ->default('America/Lima')
                    ->required()
                    ->dehydrated(false),

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

                Section::make('Numeración de pedidos')
                    ->description('Desde qué número empiezan tus pedidos y con cuántos dígitos se muestran. Solo afecta a los pedidos nuevos.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('order_number_start')->label('Número inicial')
                            ->helperText('Ej: 1000 para que el primer pedido sea el 1000.')
                            ->numeric()->minValue(1)->default(1)->dehydrated(false),
                        TextInput::make('order_number_padding')->label('Dígitos (relleno con ceros)')
                            ->helperText('Ej: 8 muestra "00001000". Deja 5 para "01000".')
                            ->numeric()->minValue(1)->maxValue(12)->default(5)->dehydrated(false),
                    ]),

                Section::make('Reparto (envío por distancia)')
                    ->description('Cobra el envío según la distancia desde tu local. La primera banda (S/ 0) es el radio gratis; más allá de la última banda, la dirección queda "fuera de cobertura". Si no configuras esto, se usan las zonas de entrega.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('delivery_center')->label('Centro de reparto (tu local)')
                            ->helperText('Pega "lat, lng" o un enlace de Google Maps de tu local.')
                            ->placeholder('-12.0650, -75.2049')
                            ->columnSpanFull()->dehydrated(false),
                        TextInput::make('delivery_free_over')->label('Envío gratis desde (S/)')
                            ->helperText('Pedidos de este monto a más no pagan envío. Vacío = desactivado.')
                            ->numeric()->minValue(0)->dehydrated(false),
                        Repeater::make('delivery_bands')->label('Bandas de distancia')
                            ->helperText('De menor a mayor. Ej: hasta 2 km → S/ 0 (gratis), hasta 4 km → S/ 2, hasta 6 km → S/ 3.')
                            ->columnSpanFull()->dehydrated(false)->defaultItems(0)->addActionLabel('Agregar banda')
                            ->schema([
                                TextInput::make('to_km')->label('Hasta (km)')->numeric()->minValue(0.1)->required(),
                                TextInput::make('fee')->label('Costo (S/)')->numeric()->minValue(0)->required(),
                            ])->columns(2),
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
            'timezone' => filled($state['timezone'] ?? null) ? $state['timezone'] : 'America/Lima',
            'geo_city' => filled($state['geo_city'] ?? null) ? trim($state['geo_city']) : null,
            'geo_region' => filled($state['geo_region'] ?? null) ? trim($state['geo_region']) : null,
            'geo_country' => filled($state['geo_country'] ?? null) ? trim($state['geo_country']) : null,
            'geo_viewbox' => filled($state['geo_viewbox'] ?? null) ? trim($state['geo_viewbox']) : null,
            'order_number_start' => max(1, (int) ($state['order_number_start'] ?? 1)),
            'order_number_padding' => min(12, max(1, (int) ($state['order_number_padding'] ?? 5))),
            'delivery_center_lat' => $this->parseCenter($state['delivery_center'] ?? null)[0],
            'delivery_center_lng' => $this->parseCenter($state['delivery_center'] ?? null)[1],
            'delivery_bands' => $this->cleanBands($state['delivery_bands'] ?? []),
            'delivery_free_over' => (isset($state['delivery_free_over']) && $state['delivery_free_over'] !== '' && $state['delivery_free_over'] !== null)
                ? round((float) $state['delivery_free_over'], 2)
                : null,
        ]);

        $record = $this->getRecord();
        $record->fill($data);
        $record->save();

        Notification::make()
            ->success()
            ->title('Configuración guardada')
            ->send();
    }

    /**
     * Extrae lat/lng de un texto: "lat, lng" o un enlace de Google Maps.
     *
     * @return array{0: ?float, 1: ?float}
     */
    private function parseCenter(?string $s): array
    {
        if (! filled($s)) {
            return [null, null];
        }

        preg_match_all('/-?\d+\.\d+/', $s, $m);
        $nums = $m[0] ?? [];

        if (count($nums) >= 2) {
            return [(float) $nums[0], (float) $nums[1]];
        }

        return [null, null];
    }

    /**
     * Normaliza las bandas: a números, sin vacías, ordenadas por distancia.
     *
     * @return array<int, array{to_km: float, fee: float}>|null
     */
    private function cleanBands(array $bands): ?array
    {
        $clean = collect($bands)
            ->map(fn ($b): array => ['to_km' => (float) ($b['to_km'] ?? 0), 'fee' => round((float) ($b['fee'] ?? 0), 2)])
            ->filter(fn (array $b): bool => $b['to_km'] > 0)
            ->sortBy('to_km')
            ->values()
            ->all();

        return empty($clean) ? null : $clean;
    }
}
