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
 * Playground: probar al agente dentro del panel, sin WhatsApp.
 *
 * El envío es en dos pasos para que se sienta en tiempo real: send() muestra el
 * mensaje del cliente al instante y dispara runAgent(), que hace la llamada
 * (lenta) al LLM y luego muestra la respuesta.
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

    /** @var array<int, array{role: string, content: string, time: string}> */
    public array $thread = [];

    /** @var array<int, array<string, mixed>> */
    public array $lastTrace = [];

    public bool $debug = false;

    public bool $pending = false;

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
        $this->pending = false;
    }

    /** Paso 1: registra el mensaje del cliente y lo muestra al instante. */
    public function send(): void
    {
        $text = trim($this->input);
        if ($text === '' || $this->pending) {
            return;
        }

        $conversation = Conversation::findOrFail($this->conversationId);
        $conversation->update(['phone' => $this->simPhone]);

        $conversation->messages()->create([
            'tenant_id' => $conversation->tenant_id,
            'role' => 'user',
            'content' => $text,
        ]);
        $conversation->update(['last_activity_at' => now()]);

        $this->input = '';
        $this->pending = true;
        $this->refreshThread();

        // Dispara el turno del agente después de pintar el mensaje del cliente.
        $this->dispatch('run-agent');
    }

    /** Paso 2: corre el agente sobre el mensaje ya guardado y muestra la respuesta. */
    public function runAgent(AgentService $agent): void
    {
        if (! $this->pending) {
            return;
        }

        $conversation = Conversation::findOrFail($this->conversationId);
        $customer = Customer::where('phone', $this->simPhone)->first();

        $context = new AgentContext(
            tenant: Filament::getTenant(),
            conversation: $conversation,
            customer: $customer,
        );

        $agent->respond($context);

        $this->lastTrace = $agent->getLastToolTrace();
        $this->pending = false;
        $this->refreshThread();
    }

    private function refreshThread(): void
    {
        $conversation = Conversation::findOrFail($this->conversationId);

        $this->thread = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('id')
            ->get(['role', 'content', 'created_at'])
            ->map(fn ($m): array => [
                'role' => $m->role,
                'content' => (string) $m->content,
                'time' => $m->created_at?->format('H:i') ?? '',
            ])
            ->all();
    }
}
