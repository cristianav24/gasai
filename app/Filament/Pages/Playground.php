<?php

namespace App\Filament\Pages;

use App\Models\Conversation;
use App\Models\Customer;
use App\Services\Agent\AgentContext;
use App\Services\Agent\AgentService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Playground: probar al agente dentro del panel, sin WhatsApp. Corre síncrono
 * (respuesta inmediata); la cola y el debounce son para WhatsApp (Fase 5).
 */
class Playground extends Page
{
    protected string $view = 'filament.pages.playground';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?string $title = 'Playground';

    protected static ?string $navigationLabel = 'Playground';

    protected static ?int $navigationSort = 10;

    /** Teléfono simulado del cliente (como el remitente en WhatsApp). */
    public string $simPhone = '+51999999999';

    public string $input = '';

    /** @var array<int, array{role: string, content: string}> */
    public array $thread = [];

    /** @var array<int, array<string, mixed>> */
    public array $lastTrace = [];

    public bool $debug = false;

    public ?int $conversationId = null;

    public function mount(): void
    {
        $this->startNewConversation();
    }

    public function startNewConversation(): void
    {
        $conversation = Conversation::create([
            'tenant_id' => Filament::getTenant()->getKey(),
            'phone' => $this->simPhone,
            'channel' => 'playground',
            'status' => 'bot',
            'last_activity_at' => now(),
        ]);

        $this->conversationId = $conversation->id;
        $this->thread = [];
        $this->lastTrace = [];
        $this->input = '';
    }

    public function send(AgentService $agent): void
    {
        $text = trim($this->input);
        if ($text === '') {
            return;
        }

        $conversation = Conversation::findOrFail($this->conversationId);
        $conversation->update(['phone' => $this->simPhone]);

        // Como en WhatsApp: el teléfono del remitente se conoce, así que si ya
        // hay un cliente con ese número, entra identificado.
        $customer = Customer::where('phone', $this->simPhone)->first();

        $context = new AgentContext(
            tenant: Filament::getTenant(),
            conversation: $conversation,
            customer: $customer,
        );

        $agent->handle($context, $text);

        $this->lastTrace = $agent->getLastToolTrace();
        $this->input = '';
        $this->refreshThread();
    }

    private function refreshThread(): void
    {
        $conversation = Conversation::findOrFail($this->conversationId);

        $this->thread = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('id')
            ->get(['role', 'content'])
            ->map(fn ($m): array => ['role' => $m->role, 'content' => (string) $m->content])
            ->all();
    }
}
