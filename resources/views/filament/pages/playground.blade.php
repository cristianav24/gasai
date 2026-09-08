<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4" style="max-width: 820px;">

        {{-- Barra superior: teléfono simulado + nueva conversación --}}
        <div class="flex items-end gap-3">
            <div class="flex-1">
                <label class="text-sm font-medium">Teléfono del cliente (simulado)</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="simPhone" />
                </x-filament::input.wrapper>
            </div>
            <x-filament::button color="gray" wire:click="startNewConversation">
                Nueva conversación
            </x-filament::button>
        </div>

        {{-- Hilo de mensajes --}}
        <div class="rounded-xl border p-4 space-y-3" style="min-height: 320px; background: rgba(0,0,0,0.02);">
            @forelse ($thread as $msg)
                <div class="flex {{ $msg['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div class="rounded-2xl px-4 py-2 text-sm"
                         style="max-width: 75%; white-space: pre-wrap; {{ $msg['role'] === 'user'
                            ? 'background:#f59e0b;color:#111;'
                            : 'background:#fff;border:1px solid #e5e7eb;' }}">
                        {{ $msg['content'] }}
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500">Escribe un mensaje para empezar a conversar con tu agente.</p>
            @endforelse
        </div>

        {{-- Entrada --}}
        <form wire:submit="send" class="flex items-end gap-3">
            <div class="flex-1">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="text"
                        wire:model="input"
                        placeholder="Escribe como si fueras el cliente…"
                    />
                </x-filament::input.wrapper>
            </div>
            <x-filament::button type="submit" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="send">Enviar</span>
                <span wire:loading wire:target="send">Pensando…</span>
            </x-filament::button>
        </form>

        {{-- Modo debug: herramientas que llamó el agente --}}
        <div>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model.live="debug" />
                Mostrar herramientas usadas (debug)
            </label>

            @if ($debug && ! empty($lastTrace))
                <div class="mt-2 rounded-lg border p-3 text-xs" style="background:#0b1020;color:#d1d5db;overflow:auto;">
                    @foreach ($lastTrace as $step)
                        <div class="mb-2">
                            <span style="color:#fbbf24;font-weight:600;">{{ $step['tool'] }}</span>
                            <div>args: {{ json_encode($step['arguments'], JSON_UNESCAPED_UNICODE) }}</div>
                            <div>result: {{ json_encode($step['result'], JSON_UNESCAPED_UNICODE) }}</div>
                        </div>
                    @endforeach
                </div>
            @elseif ($debug)
                <p class="mt-2 text-xs text-gray-500">El último turno no usó herramientas.</p>
            @endif
        </div>

    </div>
</x-filament-panels::page>
