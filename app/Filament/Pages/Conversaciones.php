<?php

namespace App\Filament\Pages;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappAccount;
use App\Services\WhatsApp\WhatsAppGateway;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Bandeja de handoff: el operador ve las conversaciones, toma el control de un
 * hilo (pasa a modo humano), responde manualmente y luego lo devuelve al agente.
 */
class Conversaciones extends Page
{
    protected string $view = 'filament.pages.conversaciones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?string $title = 'Conversaciones';

    protected static ?string $navigationLabel = 'Conversaciones';

    protected static ?int $navigationSort = 9;

    public function getMaxContentWidth(): \Filament\Support\Enums\Width|string|null
    {
        return \Filament\Support\Enums\Width::Full;
    }

    public function getHeading(): string
    {
        return '';
    }

    public ?int $selectedId = null;

    public string $draft = '';

    /** Colapsar la lista de conversaciones para dar más espacio al chat. */
    public bool $listCollapsed = false;

    public function toggleList(): void
    {
        $this->listCollapsed = ! $this->listCollapsed;
    }

    /** Pestaña activa: all | bot | humano. */
    public string $filter = 'all';

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    /**
     * Escucha en tiempo real (WebSocket / Reverb) los cambios de conversaciones
     * del tenant. Al llegar un evento, Livewire re-renderiza y la bandeja se
     * actualiza sola, sin recargar la página.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $listeners = parent::getListeners();

        if ($tenantId = Filament::getTenant()?->getKey()) {
            $listeners["echo-private:tenant.{$tenantId},.conversation.updated"] = 'onRealtimeUpdate';
        }

        return $listeners;
    }

    public function onRealtimeUpdate(): void
    {
        // El solo hecho de que Livewire llame este método re-renderiza la vista,
        // recalculando la lista de conversaciones y el hilo abierto.
    }

    /** Lista de conversaciones del tenant, filtrada por pestaña. */
    public function conversations(): Collection
    {
        return Conversation::query()
            ->with(['customer', 'lastMessage'])
            ->when($this->filter === 'bot', fn ($q) => $q->where('status', 'bot'))
            ->when($this->filter === 'humano', fn ($q) => $q->where('status', 'humano'))
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->limit(80)
            ->get();
    }

    /** Contadores para las pestañas. */
    public function counts(): array
    {
        $rows = Conversation::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'all' => (int) $rows->sum(),
            'bot' => (int) ($rows['bot'] ?? 0),
            'humano' => (int) ($rows['humano'] ?? 0),
        ];
    }

    public function selected(): ?Conversation
    {
        return $this->selectedId
            ? Conversation::with('customer')->find($this->selectedId)
            : null;
    }

    /**
     * Datos del cliente de la conversación seleccionada para el panel lateral.
     *
     * @return array<string, mixed>|null
     */
    public function customerPanel(): ?array
    {
        $conversation = $this->selected();
        if (! $conversation) {
            return null;
        }

        $customer = $conversation->customer;

        if (! $customer) {
            // Contacto no registrado: mostramos el nombre de WhatsApp si vino,
            // y el teléfono o el ID según lo que traiga WhatsApp.
            return [
                'registered' => false,
                'name' => $conversation->contact_name,
                'phone' => $conversation->phone ?: $conversation->wa_user_id,
            ];
        }

        $addresses = $customer->addresses()
            ->orderByDesc('is_primary')->orderByDesc('created_at')
            ->get(['address', 'reference', 'is_primary']);

        $envases = \App\Models\ContainerBalance::withoutGlobalScopes()
            ->with('containerType')
            ->where('customer_id', $customer->id)
            ->where('balance', '!=', 0)
            ->get();

        $ultimoPedido = \App\Models\Order::where('customer_id', $customer->id)
            ->latest('id')->first(['id', 'status', 'total', 'scheduled_date']);

        return [
            'registered' => true,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'notes' => $customer->notes,
            'addresses' => $addresses,
            'envases' => $envases,
            'orders_count' => \App\Models\Order::where('customer_id', $customer->id)->count(),
            'last_order' => $ultimoPedido,
        ];
    }

    /** @return Collection<int, Message> */
    public function thread(): Collection
    {
        if (! $this->selectedId) {
            return collect();
        }

        return Message::where('conversation_id', $this->selectedId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('id')
            ->get();
    }

    public function select(int $id): void
    {
        $this->selectedId = $id;
        $this->draft = '';
    }

    /**
     * Borrado lógico: la conversación se oculta de la bandeja (soft delete). El
     * historial no se pierde; si el contacto vuelve a escribir, empieza una nueva.
     */
    public function deleteConversation(int $id): void
    {
        $conversation = Conversation::find($id);
        if (! $conversation) {
            return;
        }

        $conversation->delete();

        if ($this->selectedId === $id) {
            $this->selectedId = null;
            $this->draft = '';
        }

        Notification::make()->success()->title('Conversación eliminada')->send();
    }

    public function takeControl(): void
    {
        $conversation = $this->selected();
        if (! $conversation) {
            return;
        }

        $conversation->update([
            'status' => 'humano',
            'assigned_user_id' => auth()->id(),
        ]);

        Notification::make()->success()->title('Tomaste el control de la conversación')->send();
    }

    public function returnToBot(): void
    {
        $conversation = $this->selected();
        if (! $conversation) {
            return;
        }

        $conversation->update([
            'status' => 'bot',
            'assigned_user_id' => null,
        ]);

        Notification::make()->success()->title('Conversación devuelta al agente')->send();
    }

    public function sendMessage(WhatsAppGateway $gateway): void
    {
        $conversation = $this->selected();
        $text = trim($this->draft);

        if (! $conversation || $text === '') {
            return;
        }

        // Registramos el mensaje del operador como saliente (assistant).
        $conversation->messages()->create([
            'tenant_id' => $conversation->tenant_id,
            'role' => 'assistant',
            'content' => $text,
        ]);
        $conversation->update(['last_activity_at' => now()]);

        // Envío real solo por WhatsApp y dentro de la ventana de 24 h.
        if ($conversation->channel === 'whatsapp') {
            if (! $conversation->within24hWindow()) {
                Notification::make()->warning()
                    ->title('Fuera de la ventana de 24h')
                    ->body('El mensaje quedó registrado pero WhatsApp no permite texto libre fuera de 24h.')
                    ->send();
            } else {
                $account = WhatsappAccount::where('tenant_id', $conversation->tenant_id)->first();
                $recipient = $conversation->phone ?: $conversation->wa_user_id;
                if ($account && filled($recipient)) {
                    $result = $gateway->sendText($account, $recipient, $text);
                    if (! ($result['ok'] ?? false)) {
                        Notification::make()->danger()->title('No se pudo enviar por WhatsApp')
                            ->body($result['error'] ?? '')->send();
                    }
                }
            }
        }

        $this->draft = '';
    }
}
