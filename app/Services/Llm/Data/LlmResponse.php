<?php

namespace App\Services\Llm\Data;

/**
 * Respuesta de un turno del LLM: o bien texto para el cliente, o bien una o
 * más llamadas a herramientas que el agente debe ejecutar.
 */
class LlmResponse
{
    /**
     * @param  ToolCall[]  $toolCalls
     */
    public function __construct(
        public ?string $content,
        public array $toolCalls = [],
        public ?string $finishReason = null,
        public array $raw = [],
    ) {}

    public function hasToolCalls(): bool
    {
        return count($this->toolCalls) > 0;
    }
}
