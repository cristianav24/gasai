<?php

namespace App\Services\Agent;

use App\Services\Agent\Tools\Tool;

/**
 * Registro de herramientas disponibles para el agente. Traduce las herramientas
 * al formato de "tools" que espera la API (estilo OpenAI) y las ejecuta por nombre.
 */
class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    /**
     * @param  iterable<Tool>  $tools
     */
    public function __construct(iterable $tools = [])
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    public function register(Tool $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): ?Tool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Definiciones en formato API (estilo OpenAI function calling).
     *
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        return array_map(fn (Tool $tool): array => [
            'type' => 'function',
            'function' => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->parameters(),
            ],
        ], array_values($this->tools));
    }
}
