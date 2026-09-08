<?php

namespace App\Services\Llm\Data;

/**
 * Una petición del LLM para ejecutar una herramienta.
 */
class ToolCall
{
    public function __construct(
        public string $id,          // id de la llamada (lo exige el protocolo al devolver el resultado)
        public string $name,        // nombre de la herramienta
        public array $arguments,    // argumentos ya decodificados de JSON
    ) {}
}
