<?php

namespace Tests\Support;

use App\Services\Llm\Data\LlmResponse;
use App\Services\Llm\Data\ToolCall;
use App\Services\Llm\LlmProvider;

/**
 * Proveedor de LLM falso para tests: devuelve respuestas programadas en orden,
 * sin tocar la red. Permite probar el loop del agente de forma determinista.
 */
class FakeLlmProvider implements LlmProvider
{
    /** @var LlmResponse[] */
    private array $queue;

    /** @var array<int, array<string, mixed>> Cada llamada recibida (para aserciones). */
    public array $calls = [];

    public function __construct(LlmResponse ...$responses)
    {
        $this->queue = $responses;
    }

    /** Respuesta de solo texto. */
    public static function text(string $content): LlmResponse
    {
        return new LlmResponse(content: $content, finishReason: 'stop');
    }

    /**
     * Respuesta con una llamada a herramienta.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function toolCall(string $name, array $arguments, string $id = 'call_1'): LlmResponse
    {
        return new LlmResponse(
            content: null,
            toolCalls: [new ToolCall(id: $id, name: $name, arguments: $arguments)],
            finishReason: 'tool_calls',
        );
    }

    public function chat(array $messages, array $tools = [], array $options = []): LlmResponse
    {
        $this->calls[] = ['messages' => $messages, 'tools' => $tools, 'options' => $options];

        if (! empty($this->queue)) {
            return array_shift($this->queue);
        }

        // Si se agota la cola, seguimos pidiendo herramientas: útil para probar
        // el escalado por máximo de iteraciones.
        return self::toolCall('listar_productos', []);
    }
}
