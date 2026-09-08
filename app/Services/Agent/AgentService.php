<?php

namespace App\Services\Agent;

use App\Models\BotConfig;
use App\Models\Message;
use App\Services\Llm\Data\LlmResponse;
use App\Services\Llm\Data\ToolCall;
use App\Services\Llm\LlmProvider;
use Throwable;

/**
 * Orquesta un turno del agente: arma el prompt, corre el loop de tool calls y
 * devuelve la respuesta para el cliente.
 *
 * Reglas duras: máximo de iteraciones y timeout por turno; si se pasa, escala a
 * humano. El tenant sale del AgentContext, no de los argumentos del modelo.
 */
class AgentService
{
    /** Máximo de rondas de tool calls por turno. */
    private const MAX_ITERATIONS = 6;

    /** @var array<int, array{tool: string, arguments: array<string, mixed>, result: array<string, mixed>}> */
    private array $toolTrace = [];

    public function __construct(
        private LlmProvider $llm,
        private ToolRegistry $tools,
        private SystemPromptBuilder $promptBuilder,
    ) {}

    /**
     * Herramientas que se ejecutaron en el último turno (para el modo debug del playground).
     *
     * @return array<int, array{tool: string, arguments: array<string, mixed>, result: array<string, mixed>}>
     */
    public function getLastToolTrace(): array
    {
        return $this->toolTrace;
    }

    /**
     * Procesa un mensaje entrante y devuelve el texto de respuesta del agente.
     */
    public function handle(AgentContext $context, string $incomingText): string
    {
        // Persistimos el mensaje entrante primero (queda registrado pase lo que pase).
        $this->persist($context, 'user', $incomingText);

        return $this->respond($context);
    }

    /**
     * Corre un turno del agente asumiendo que el/los mensajes entrantes YA están
     * en el historial (caso WhatsApp: el webhook los guardó). Devuelve la respuesta.
     */
    public function respond(AgentContext $context): string
    {
        $this->toolTrace = [];

        $messages = array_merge(
            [['role' => 'system', 'content' => $this->promptBuilder->build($context)]],
            $this->loadHistory($context),
        );

        $definitions = $this->tools->definitions();
        $temperature = (float) (BotConfig::first()?->temperature ?? 0.3);

        $deadline = microtime(true) + (float) config('services.deepseek.timeout', 30) * self::MAX_ITERATIONS;

        for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
            if (microtime(true) > $deadline) {
                return $this->escalate($context, 'Se agotó el tiempo del turno.');
            }

            try {
                $response = $this->llm->chat($messages, $definitions, ['temperature' => $temperature]);
            } catch (Throwable $e) {
                report($e);
                return $this->escalate($context, 'Error al contactar al modelo.');
            }

            if (! $response->hasToolCalls()) {
                $text = trim((string) $response->content);
                if ($text === '') {
                    $text = '¿Podrías repetirme eso, por favor?';
                }
                $this->persist($context, 'assistant', $text);

                return $text;
            }

            // El modelo pidió herramientas: las anexamos y ejecutamos.
            $messages[] = $this->assistantToolCallsMessage($response);

            foreach ($response->toolCalls as $call) {
                $result = $this->executeTool($call, $context);
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call->id,
                    'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        // Se pasó del máximo de iteraciones: escalamos.
        return $this->escalate($context, 'Se alcanzó el máximo de pasos del agente.');
    }

    /**
     * Ejecuta una herramienta de forma segura. Si falla o no existe, devuelve un
     * resultado de error para que el modelo lo maneje en vez de reventar el turno.
     *
     * @return array<string, mixed>
     */
    private function executeTool(ToolCall $call, AgentContext $context): array
    {
        $tool = $this->tools->get($call->name);

        if (! $tool) {
            return ['error' => "La herramienta '{$call->name}' no existe."];
        }

        try {
            $result = $tool->handle($call->arguments, $context);
        } catch (Throwable $e) {
            report($e);
            $result = ['error' => 'La herramienta falló al ejecutarse.'];
        }

        $this->toolTrace[] = [
            'tool' => $call->name,
            'arguments' => $call->arguments,
            'result' => $result,
        ];

        return $result;
    }

    /**
     * Mensaje de asistente con las llamadas a herramientas, en el formato de la API.
     *
     * @return array<string, mixed>
     */
    private function assistantToolCallsMessage(LlmResponse $response): array
    {
        return [
            'role' => 'assistant',
            'content' => $response->content,
            'tool_calls' => array_map(fn (ToolCall $c): array => [
                'id' => $c->id,
                'type' => 'function',
                'function' => [
                    'name' => $c->name,
                    'arguments' => json_encode($c->arguments, JSON_UNESCAPED_UNICODE),
                ],
            ], $response->toolCalls),
        ];
    }

    /**
     * Historial de la conversación en formato chat (solo user/assistant con texto).
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadHistory(AgentContext $context): array
    {
        return $context->conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->whereNotNull('content')
            ->orderBy('id')
            ->get(['role', 'content'])
            ->map(fn (Message $m): array => ['role' => $m->role, 'content' => $m->content])
            ->all();
    }

    private function escalate(AgentContext $context, string $motivo): string
    {
        $context->conversation->update(['status' => 'humano']);

        $text = 'Déjame derivarte con una persona del equipo para ayudarte mejor.';
        $this->persist($context, 'assistant', $text);

        return $text;
    }

    private function persist(AgentContext $context, string $role, string $content): void
    {
        $context->conversation->messages()->create([
            'tenant_id' => $context->tenantId(),
            'role' => $role,
            'content' => $content,
        ]);

        $context->conversation->update(['last_activity_at' => now()]);
    }
}
