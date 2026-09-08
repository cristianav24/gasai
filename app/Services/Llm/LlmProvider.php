<?php

namespace App\Services\Llm;

use App\Services\Llm\Data\LlmResponse;

/**
 * Interfaz del proveedor de LLM. El agente solo conoce esto; el proveedor
 * concreto (DeepSeek, OpenAI, etc.) se resuelve por el contenedor.
 */
interface LlmProvider
{
    /**
     * Envía un turno de conversación al modelo.
     *
     * @param  array<int, array<string, mixed>>  $messages  Historial en formato chat (role/content/…).
     * @param  array<int, array<string, mixed>>  $tools     Definiciones de herramientas (schema estilo OpenAI).
     * @param  array<string, mixed>  $options               Opciones (temperature, etc.).
     */
    public function chat(array $messages, array $tools = [], array $options = []): LlmResponse;
}
