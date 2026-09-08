<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentContext;

/**
 * Una herramienta que el agente puede llamar (function calling).
 */
interface Tool
{
    /** Nombre único de la herramienta (el que ve el modelo). */
    public function name(): string;

    /** Descripción para el modelo: cuándo y para qué usarla. */
    public function description(): string;

    /**
     * Schema JSON de los parámetros (estilo OpenAI/JSON Schema).
     *
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * Ejecuta la herramienta. Devuelve un array serializable a JSON que se le
     * entrega al modelo como resultado.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function handle(array $arguments, AgentContext $context): array;
}
