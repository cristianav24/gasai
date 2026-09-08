<?php

namespace App\Filament\Pages;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappAccount;
use App\Services\WhatsApp\WhatsAppGateway;
use BackedEnum;
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

    public ?int $selectedId = null;

    public string $draft = '';

    /** Lista de conversaciones del tenant, más recientes primero. */
    public function conversations(): Collection
    {
        return Conversation::query()
            ->with('customer')
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    public function selected(): ?Conversation
    {
        return $this->selectedId
            ? Conversation::with('customer')->find($this->selectedId)
            : null;
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
                if ($account) {
                    $result = $gateway->sendText($account, $conversation->phone, $text);
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
