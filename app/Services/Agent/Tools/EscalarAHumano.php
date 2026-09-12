<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentContext;
use App\Services\Push\PushDispatcher;

/**
 * Marca la conversación para que la atienda un humano. La bandeja de handoff
 * completa llega en la Fase 9; aquí ya dejamos la conversación en estado 'humano'.
 */
class EscalarAHumano implements Tool
{
    public function name(): string
    {
        return 'escalar_a_humano';
    }

    public function description(): string
    {
        return 'Deriva la conversación a un operador humano cuando no puedes resolver la solicitud, '
            . 'el cliente lo pide, o hay un problema que requiere una persona.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'motivo' => ['type' => 'string', 'description' => 'Motivo del escalamiento.'],
            ],
            'required' => ['motivo'],
        ];
    }

    public function handle(array $arguments, AgentContext $context): array
    {
        $context->conversation->update(['status' => 'humano']);

        $motivo = (string) ($arguments['motivo'] ?? '');

        // Avisa por push a dueños/operadores que el bot pide intervención humana.
        try {
            app(PushDispatcher::class)->notifyHandoff($context->conversation, $motivo);
        } catch (\Throwable $e) {
            // El push es un extra: nunca debe romper el flujo del agente.
            report($e);
        }

        return [
            'ok' => true,
            'mensaje' => 'La conversación fue derivada a un operador humano.',
            'motivo' => $motivo,
        ];
    }
}
