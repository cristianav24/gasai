<x-filament-panels::page>
    <style>
        .gasai-pg {
            --pg-surface: #ffffff; --pg-surface-2: #f8fafc; --pg-text: #0f172a;
            --pg-muted: #64748b; --pg-border: #e5e7eb;
            --pg-bot-bg: #ffffff; --pg-bot-text: #0f172a; --pg-bot-border: #e5e7eb;
        }
        .dark .gasai-pg {
            --pg-surface: #1f2937; --pg-surface-2: #111827; --pg-text: #f3f4f6;
            --pg-muted: #9ca3af; --pg-border: #374151;
            --pg-bot-bg: #374151; --pg-bot-text: #f3f4f6; --pg-bot-border: #4b5563;
        }
        .gasai-pg .pg-thread {
            background: var(--pg-surface-2); border: 1px solid var(--pg-border);
            border-radius: 0.9rem; padding: 1rem; height: 52vh; min-height: 320px;
            overflow-y: auto; display: flex; flex-direction: column; gap: 0.6rem;
        }
        .gasai-pg .pg-row { display: flex; align-items: flex-end; gap: 0.5rem; }
        .gasai-pg .pg-row.user { flex-direction: row-reverse; }
        .gasai-pg .pg-avatar {
            width: 28px; height: 28px; border-radius: 50%; flex: 0 0 28px;
            display: flex; align-items: center; justify-content: center; font-size: 14px;
        }
        .gasai-pg .pg-bubble {
            max-width: 74%; padding: 0.55rem 0.85rem; border-radius: 1rem;
            font-size: 0.9rem; line-height: 1.35; white-space: pre-wrap; word-break: break-word;
        }
        .gasai-pg .pg-bubble.bot {
            background: var(--pg-bot-bg); color: var(--pg-bot-text);
            border: 1px solid var(--pg-bot-border); border-bottom-left-radius: 0.25rem;
        }
        .gasai-pg .pg-bubble.user {
            background: #f59e0b; color: #1c1917; border-bottom-right-radius: 0.25rem;
        }
        .gasai-pg .pg-empty { margin: auto; color: var(--pg-muted); font-size: 0.9rem; text-align: center; }
        .gasai-pg .pg-debug {
            background: #0b1020; color: #e2e8f0; border-radius: 0.6rem; padding: 0.75rem;
            font-size: 0.72rem; overflow: auto; border: 1px solid #1e293b;
        }
        .gasai-pg .pg-debug .k { color: #fbbf24; font-weight: 700; }
        .gasai-pg .pg-debug .m { color: #94a3b8; }
    </style>

    <div class="gasai-pg" style="max-width: 860px; display: grid; gap: 1rem;">

        {{-- Barra superior --}}
        <x-filament::section>
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <label class="text-sm font-medium">Teléfono del cliente (simulado)</label>
                    <x-filament::input.wrapper class="mt-1">
                        <x-filament::input type="text" wire:model="simPhone" />
                    </x-filament::input.wrapper>
                    <p class="text-xs mt-1" style="color: var(--pg-muted);">
                        Simula el número del cliente. Si ya existe, el agente lo reconoce.
                    </p>
                </div>
                <x-filament::button color="gray" icon="heroicon-o-arrow-path" wire:click="startNewConversation">
                    Nueva conversación
                </x-filament::button>
            </div>
        </x-filament::section>

        {{-- Hilo de mensajes con auto-scroll --}}
        <div
            x-data="{}"
            x-init="() => {
                const el = $refs.thread;
                const toBottom = () => el.scrollTop = el.scrollHeight;
                toBottom();
                new MutationObserver(toBottom).observe(el, { childList: true, subtree: true });
            }"
        >
            <div class="pg-thread" x-ref="thread">
                @forelse ($thread as $msg)
                    <div class="pg-row {{ $msg['role'] === 'user' ? 'user' : 'bot' }}">
                        <div class="pg-avatar" style="background: {{ $msg['role'] === 'user' ? '#fde68a' : 'var(--pg-bot-bg)' }}; border: 1px solid var(--pg-border);">
                            {{ $msg['role'] === 'user' ? '🧑' : '🤖' }}
                        </div>
                        <div class="pg-bubble {{ $msg['role'] === 'user' ? 'user' : 'bot' }}">{{ $msg['content'] }}</div>
                    </div>
                @empty
                    <div class="pg-empty">
                        💬 Escribe un mensaje para empezar a conversar con tu agente.<br>
                        Prueba con: <em>"Hola, ¿qué venden?"</em>
                    </div>
                @endforelse

                {{-- Indicador de "pensando" --}}
                <div wire:loading wire:target="send" class="pg-row bot">
                    <div class="pg-avatar" style="background: var(--pg-bot-bg); border: 1px solid var(--pg-border);">🤖</div>
                    <div class="pg-bubble bot" style="color: var(--pg-muted);">Pensando…</div>
                </div>
            </div>
        </div>

        {{-- Entrada --}}
        <form wire:submit="send" class="flex items-end gap-3">
            <div class="flex-1">
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="input" placeholder="Escribe como si fueras el cliente…" />
                </x-filament::input.wrapper>
            </div>
            <x-filament::button type="submit" icon="heroicon-o-paper-airplane" wire:loading.attr="disabled" wire:target="send">
                <span wire:loading.remove wire:target="send">Enviar</span>
                <span wire:loading wire:target="send">Enviando…</span>
            </x-filament::button>
        </form>

        {{-- Modo debug --}}
        <div>
            <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" wire:model.live="debug" />
                Mostrar herramientas usadas (debug)
            </label>

            @if ($debug && ! empty($lastTrace))
                <div class="pg-debug mt-2">
                    @foreach ($lastTrace as $step)
                        <div class="mb-2">
                            <span class="k">{{ $step['tool'] }}</span>
                            <div><span class="m">args:</span> {{ json_encode($step['arguments'], JSON_UNESCAPED_UNICODE) }}</div>
                            <div><span class="m">result:</span> {{ json_encode($step['result'], JSON_UNESCAPED_UNICODE) }}</div>
                        </div>
                    @endforeach
                </div>
            @elseif ($debug)
                <p class="mt-2 text-xs" style="color: var(--pg-muted);">El último turno no usó herramientas.</p>
            @endif
        </div>

    </div>
</x-filament-panels::page>
