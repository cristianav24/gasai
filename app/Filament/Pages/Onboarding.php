<?php

namespace App\Filament\Pages;

use App\Models\BotConfig;
use App\Models\DeliveryZone;
use App\Models\KnowledgeItem;
use App\Models\Product;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;

/**
 * Onboarding guiado para el dueño del negocio. Cada paso guarda a los modelos
 * reales al avanzar (afterValidation) y actualiza tenant.onboarding_step, para
 * poder retomar donde se quedó. Todos los pasos son saltables salvo "Tu negocio".
 */
class Onboarding extends Page
{
    protected string $view = 'filament.pages.onboarding';

    protected static bool $shouldRegisterNavigation = false; // No aparece en el menú.

    protected static ?string $title = 'Configura tu negocio';

    /** El hero de la vista ya da el título; evitamos el encabezado duplicado. */
    public function getHeading(): string
    {
        return '';
    }

    public function getMaxContentWidth(): \Filament\Support\Enums\Width|string|null
    {
        return \Filament\Support\Enums\Width::FiveExtraLarge;
    }

    /** Ejemplos de productos precargados según el rubro. */
    private const EJEMPLOS_PRODUCTOS = [
        'agua' => [
            ['name' => 'Bidón 20L nuevo', 'price' => 25, 'unit' => 'bidón', 'type' => 'venta'],
            ['name' => 'Recarga 20L', 'price' => 8, 'unit' => 'bidón', 'type' => 'recarga'],
        ],
        'gas' => [
            ['name' => 'Balón 10kg', 'price' => 45, 'unit' => 'balón', 'type' => 'venta'],
        ],
        'otro' => [],
    ];

    /**
     * Ejemplos de productos para un rubro (usado para precargar el paso 2).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function exampleProductsFor(string $rubro): array
    {
        return self::EJEMPLOS_PRODUCTOS[$rubro] ?? [];
    }

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $tenant = $this->tenant();

        // Precargamos lo que ya exista, para retomar sin perder datos.
        $this->form->fill([
            'name' => $tenant->name,
            'rubro' => $tenant->rubro,
            'business_hours_text' => is_array($tenant->business_hours)
                ? ($tenant->business_hours['texto'] ?? '')
                : (string) $tenant->business_hours,
            'timezone' => $tenant->timezone ?: 'America/Lima',
            'geo_city' => $tenant->geo_city,
            'geo_region' => $tenant->geo_region,
        ]);
    }

    protected function tenant(): Tenant
    {
        return Filament::getTenant();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    $this->pasoNegocio(),
                    $this->pasoProductos(),
                    $this->pasoZonas(),
                    $this->pasoConocimiento(),
                    $this->pasoAgente(),
                    $this->pasoPruebalo(),
                    $this->pasoWhatsapp(),
                ])
                    ->persistStepInQueryString()
                    ->submitAction($this->submitButton()),
            ])
            ->statePath('data');
    }

    private function submitButton(): \Illuminate\Support\HtmlString
    {
        return new \Illuminate\Support\HtmlString(
            '<button type="submit" '
            . 'style="display:inline-flex;align-items:center;gap:.45rem;background:linear-gradient(135deg,#f59e0b,#f97316);'
            . 'color:#1c1917;padding:.6rem 1.2rem;border:0;border-radius:.7rem;font-weight:700;cursor:pointer;'
            . 'box-shadow:0 4px 12px rgba(245,158,11,.35);">'
            . '✓ Finalizar y ir al panel</button>'
        );
    }

    // --- Paso 1: Tu negocio (obligatorio) ---
    private function pasoNegocio(): Step
    {
        return Step::make('Tu negocio')
            ->description('Lo básico para empezar')
            ->schema([
                TextInput::make('name')
                    ->label('Nombre del negocio')
                    ->required()
                    ->maxLength(255),

                Select::make('rubro')
                    ->label('¿Qué repartes?')
                    ->options(['agua' => 'Agua', 'gas' => 'Gas', 'otro' => 'Otro'])
                    ->default('agua')
                    ->required()
                    ->live(),

                TextInput::make('business_hours_text')
                    ->label('Horario de atención')
                    ->placeholder('Lun a Sáb, 8:00 a 18:00')
                    ->maxLength(255),

                Select::make('timezone')
                    ->label('Zona horaria')
                    ->options([
                        'America/Lima' => 'Perú (America/Lima)',
                        'America/Bogota' => 'Colombia (America/Bogota)',
                        'America/La_Paz' => 'Bolivia (America/La_Paz)',
                    ])
                    ->default('America/Lima')
                    ->required(),

                TextInput::make('geo_city')
                    ->label('Ciudad')
                    ->placeholder('Ej: Huancayo')
                    ->helperText('Ayuda al asistente a ubicar con exactitud las direcciones que te escriban.')
                    ->maxLength(120),

                TextInput::make('geo_region')
                    ->label('Región / Departamento')
                    ->placeholder('Ej: Junín')
                    ->maxLength(120),
            ])
            ->afterValidation(function (Set $set): void {
                $this->guardarNegocio();
                // Precargamos ejemplos de productos según el rubro (si aún no hay).
                if (empty($this->data['products'])) {
                    $set('products', self::exampleProductsFor($this->data['rubro']));
                }
            });
    }

    // --- Paso 2: Productos ---
    private function pasoProductos(): Step
    {
        return Step::make('Tus productos')
            ->description('Qué vendes y a cuánto')
            ->schema([
                Repeater::make('products')
                    ->label('Productos')
                    ->schema([
                        TextInput::make('name')->label('Nombre')->required(),
                        TextInput::make('price')->label('Precio')->numeric()->minValue(0)->required()->prefix('S/'),
                        TextInput::make('unit')->label('Unidad')->default('unidad')->required(),
                        Select::make('type')->label('Tipo')->options([
                            'venta' => 'Venta (nuevo)', 'recarga' => 'Recarga',
                        ])->default('venta')->required(),
                    ])
                    ->addActionLabel('Agregar producto')
                    ->columns(2)
                    ->default([]),
            ])
            ->afterValidation(fn () => $this->guardarProductos());
    }

    // --- Paso 3: Zonas de entrega ---
    private function pasoZonas(): Step
    {
        return Step::make('Zonas de entrega')
            ->description('Dónde repartes y cuánto cobras')
            ->schema([
                Repeater::make('zones')
                    ->label('Zonas')
                    ->schema([
                        TextInput::make('name')->label('Zona')->required(),
                        TextInput::make('delivery_fee')->label('Costo de envío')->numeric()->minValue(0)->default(0)->required()->prefix('S/'),
                        Textarea::make('coverage')->label('Cobertura')->rows(2)->columnSpanFull(),
                    ])
                    ->addActionLabel('Agregar zona')
                    ->columns(2)
                    ->default([]),
            ])
            ->afterValidation(fn () => $this->guardarZonas());
    }

    // --- Paso 4: Conocimiento (preguntas guiadas) ---
    private function pasoConocimiento(): Step
    {
        return Step::make('Conocimiento')
            ->description('Lo que el agente debe saber')
            ->schema([
                Textarea::make('know_tiempo')
                    ->label('¿En cuánto tiempo entregas normalmente?')
                    ->rows(2),
                Textarea::make('know_pagos')
                    ->label('¿Qué formas de pago aceptas? (efectivo, Yape, Plin…)')
                    ->rows(2),
                Textarea::make('know_ausente')
                    ->label('¿Qué pasa si el cliente no está en casa?')
                    ->rows(2),
                Textarea::make('know_extra')
                    ->label('Algo más que el cliente suele preguntar')
                    ->rows(2),
            ])
            ->afterValidation(fn () => $this->guardarConocimiento());
    }

    // --- Paso 5: Tu agente ---
    private function pasoAgente(): Step
    {
        return Step::make('Tu agente')
            ->description('La voz de tu negocio')
            ->schema([
                TextInput::make('agent_name')->label('Nombre del agente')->default('Asistente'),
                Select::make('tone')->label('Tono')->options([
                    'amable' => 'Amable y cercano', 'formal' => 'Formal', 'breve' => 'Breve y directo',
                ])->default('amable'),
                Textarea::make('welcome_message')
                    ->label('Mensaje de bienvenida')
                    ->placeholder('¡Hola! Soy el asistente de… ¿en qué te ayudo?')
                    ->rows(2),
            ])
            ->afterValidation(fn () => $this->guardarAgente());
    }

    // --- Paso 6: Pruébalo ---
    private function pasoPruebalo(): Step
    {
        return Step::make('Pruébalo')
            ->description('Conversa con tu agente')
            ->schema([
                \Filament\Schemas\Components\Text::make(
                    'Ya puedes probar tu agente en el Playground: escríbele como si fueras un cliente y verás cómo responde con tus productos y precios. No necesitas WhatsApp todavía.'
                ),
                \Filament\Schemas\Components\Actions::make([
                    \Filament\Actions\Action::make('ir_playground')
                        ->label('Abrir el Playground')
                        ->icon(\Filament\Support\Icons\Heroicon::OutlinedBeaker)
                        ->url(fn (): string => Playground::getUrl())
                        ->openUrlInNewTab(),
                ]),
            ]);
    }

    // --- Paso 7: Conecta WhatsApp ---
    private function pasoWhatsapp(): Step
    {
        return Step::make('Conecta tu WhatsApp')
            ->description('Puedes hacerlo después')
            ->schema([
                \Filament\Schemas\Components\Text::make(
                    'La conexión con WhatsApp la configuras más adelante desde el panel. '
                    . 'Puedes terminar el onboarding ahora y conectarla cuando estés listo; '
                    . 'te lo recordaremos en el tablero.'
                ),
            ]);
    }

    // ==================== Persistencia por paso ====================

    private function guardarNegocio(): void
    {
        $tenant = $this->tenant();
        $tenant->update([
            'name' => $this->data['name'],
            'rubro' => $this->data['rubro'],
            'business_hours' => ['texto' => $this->data['business_hours_text'] ?? ''],
            'timezone' => $this->data['timezone'],
            'geo_city' => $this->data['geo_city'] ?? null,
            'geo_region' => $this->data['geo_region'] ?? null,
            'geo_country' => filled($this->data['geo_city'] ?? null) ? ($this->tenant()->geo_country ?: 'Perú') : $this->tenant()->geo_country,
            'onboarding_step' => 'productos',
        ]);
    }

    private function guardarProductos(): void
    {
        $this->tenant()->update(['onboarding_step' => 'zonas']);

        foreach ($this->data['products'] ?? [] as $p) {
            if (blank($p['name'] ?? null)) {
                continue;
            }
            Product::updateOrCreate(
                ['tenant_id' => $this->tenant()->id, 'name' => $p['name']],
                [
                    'price' => $p['price'] ?? 0,
                    'unit' => $p['unit'] ?? 'unidad',
                    'type' => $p['type'] ?? 'venta',
                    'active' => true,
                ],
            );
        }
    }

    private function guardarZonas(): void
    {
        $this->tenant()->update(['onboarding_step' => 'conocimiento']);

        foreach ($this->data['zones'] ?? [] as $z) {
            if (blank($z['name'] ?? null)) {
                continue;
            }
            DeliveryZone::updateOrCreate(
                ['tenant_id' => $this->tenant()->id, 'name' => $z['name']],
                [
                    'delivery_fee' => $z['delivery_fee'] ?? 0,
                    'coverage' => $z['coverage'] ?? null,
                    'active' => true,
                ],
            );
        }
    }

    private function guardarConocimiento(): void
    {
        $this->tenant()->update(['onboarding_step' => 'agente']);

        $preguntas = [
            'know_tiempo' => 'Tiempo de entrega',
            'know_pagos' => 'Formas de pago',
            'know_ausente' => 'Si el cliente no está',
            'know_extra' => 'Otras preguntas frecuentes',
        ];

        foreach ($preguntas as $campo => $titulo) {
            $contenido = trim((string) ($this->data[$campo] ?? ''));
            if ($contenido === '') {
                continue;
            }
            KnowledgeItem::updateOrCreate(
                ['tenant_id' => $this->tenant()->id, 'title' => $titulo],
                ['content' => $contenido, 'active' => true],
            );
        }
    }

    private function guardarAgente(): void
    {
        $this->tenant()->update(['onboarding_step' => 'pruebalo']);

        BotConfig::updateOrCreate(
            ['tenant_id' => $this->tenant()->id],
            [
                'agent_name' => $this->data['agent_name'] ?? 'Asistente',
                'tone' => $this->data['tone'] ?? 'amable',
                'welcome_message' => $this->data['welcome_message'] ?? null,
            ],
        );
    }

    public function complete(): void
    {
        // Valida todos los pasos (incluye "Tu negocio" obligatorio).
        $this->form->getState();

        // Persistimos todo, sin importar cómo navegó el usuario por el wizard.
        $this->guardarNegocio();
        $this->guardarProductos();
        $this->guardarZonas();
        $this->guardarConocimiento();
        $this->guardarAgente();

        $this->tenant()->update([
            'onboarding_step' => 'completado',
            'onboarding_completed_at' => now(),
        ]);

        $this->redirect(\Filament\Pages\Dashboard::getUrl());
    }
}
