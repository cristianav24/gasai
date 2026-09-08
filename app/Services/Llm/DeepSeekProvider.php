<?php

namespace App\Services\Llm;

use App\Services\Llm\Data\LlmResponse;
use App\Services\Llm\Data\ToolCall;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

/**
 * Proveedor DeepSeek (API compatible con OpenAI, function calling estilo OpenAI).
 */
class DeepSeekProvider implements LlmProvider
{
    public function __construct(
        private HttpFactory $http,
        private string $apiKey,
        private string $baseUrl,
        private string $model,
        private int $timeout = 30,
    ) {}

    public function chat(array $messages, array $tools = [], array $options = []): LlmResponse
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.3,
        ];

        if (! empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = $options['tool_choice'] ?? 'auto';
        }

        $response = $this->http
            ->baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->post('/chat/completions', $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                "DeepSeek respondió {$response->status()}: " . $response->body()
            );
        }

        return $this->parse($response->json());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function parse(array $data): LlmResponse
    {
        $choice = $data['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        $toolCalls = [];
        foreach ($message['tool_calls'] ?? [] as $call) {
            $rawArgs = $call['function']['arguments'] ?? '{}';

            // Los argumentos vienen como string JSON. Parsear siempre; si el
            // modelo malforma el JSON, devolvemos args vacíos y el agente lo
            // maneja (reintento / escalado) en vez de reventar.
            $args = json_decode($rawArgs, true);
            if (! is_array($args)) {
                $args = [];
            }

            $toolCalls[] = new ToolCall(
                id: $call['id'] ?? uniqid('call_'),
                name: $call['function']['name'] ?? '',
                arguments: $args,
            );
        }

        return new LlmResponse(
            content: $message['content'] ?? null,
            toolCalls: $toolCalls,
            finishReason: $choice['finish_reason'] ?? null,
            raw: $data,
        );
    }
}
