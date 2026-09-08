<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ConexionWhatsapp;
use App\Filament\Pages\ConfiguracionBot;
use App\Filament\Resources\DeliveryZones\DeliveryZoneResource;
use App\Filament\Resources\KnowledgeItems\KnowledgeItemResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\BotConfig;
use App\Models\DeliveryZone;
use App\Models\KnowledgeItem;
use App\Models\Product;
use App\Models\WhatsappAccount;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Checklist de progreso en el dashboard: muestra lo que quedó pendiente tras el
 * onboarding, con enlaces para completarlo. Se puede descartar.
 */
class OnboardingChecklist extends Widget
{
    protected string $view = 'filament.widgets.onboarding-checklist';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10; // Arriba del todo.

    public static function canView(): bool
    {
        $tenant = Filament::getTenant();

        // Se muestra solo tras completar el onboarding y mientras no se descarte.
        return $tenant
            && $tenant->onboardingCompleted()
            && ! $tenant->onboarding_checklist_dismissed;
    }

    /**
     * @return array<int, array{label: string, done: bool, url: ?string}>
     */
    public function getItems(): array
    {
        return [
            [
                'label' => 'Cargar tus productos',
                'done' => Product::count() > 0,
                'url' => ProductResource::getUrl(),
            ],
            [
                'label' => 'Definir tus zonas de entrega',
                'done' => DeliveryZone::count() > 0,
                'url' => DeliveryZoneResource::getUrl(),
            ],
            [
                'label' => 'Agregar conocimiento de tu empresa',
                'done' => KnowledgeItem::count() > 0,
                'url' => KnowledgeItemResource::getUrl(),
            ],
            [
                'label' => 'Configurar tu agente',
                'done' => BotConfig::whereNotNull('agent_name')->exists(),
                'url' => ConfiguracionBot::getUrl(),
            ],
            [
                'label' => 'Conectar tu WhatsApp',
                'done' => WhatsappAccount::where('status', 'connected')->exists(),
                'url' => ConexionWhatsapp::getUrl(),
            ],
        ];
    }

    public function getPendingCount(): int
    {
        return collect($this->getItems())->where('done', false)->count();
    }

    public function dismiss(): void
    {
        Filament::getTenant()->update(['onboarding_checklist_dismissed' => true]);

        $this->dispatch('$refresh');
    }
}
