<x-filament-panels::page>
    <style>
        .gasai-pg {
            --pg-surface: #ffffff; --pg-surface-2: #f8fafc; --pg-text: #0f172a;
            --pg-muted: #475569; --pg-border: #e2e8f0;
            --pg-bot-bg: #ffffff; --pg-bot-text: #0f172a; --pg-bot-border: #e2e8f0;
            --pg-av-bot: #f1f5f9; --pg-av-user: #fde68a;
        }
        .dark .gasai-pg {
            --pg-surface: #1f2937; --pg-surface-2: #0f172a; --pg-text: #f1f5f9;
            --pg-muted: #94a3b8; --pg-border: #334155;
            --pg-bot-bg: #1e293b; --pg-bot-text: #f1f5f9; --pg-bot-border: #334155;
            --pg-av-bot: #334155; --pg-av-user: #92710a;
        }
        .gasai-pg .pg-thread {
            background: var(--pg-surface-2); border: 1px solid var(--pg-border);
            border-radius: 1rem; padding: 1.1rem; height: 54vh; min-height: 340px;
            overflow-y: auto; display: flex; flex-direction: column; gap: 0.85rem; scroll-behavior: smooth;
        }
        .gasai-pg .pg-row { display: flex; align-items: flex-end; gap: 0.55rem; max-width: 82%; }
        .gasai-pg .pg-row.user { flex-direction: row-reverse; align-self: flex-end; }
        .gasai-pg .pg-row.bot { align-self: flex-start; }
        .gasai-pg .pg-av {
            width: 30px; height: 30px; border-radius: 50%; flex: 0 0 30px;
            display: flex; align-items: center; justify-content: center;
            border: 1px solid var(--pg-border);
        }
        .gasai-pg .pg-av.bot { background: var(--pg-av-bot); color: var(--pg-muted); }
        .gasai-pg .pg-av.user { background: var(--pg-av-user); color: #78350f; }
        .gasai-pg .pg-av svg { width: 16px; height: 16px; }
        .gasai-pg .pg-col { display: flex; flex-direction: column; gap: 2px; }
        .gasai-pg .pg-bubble {
            padding: 0.55rem 0.85rem; border-radius: 1.1rem; font-size: 0.9rem;
            line-height: 1.4; white-space: pre-wrap; word-break: break-word;
        }
        .gasai-pg .pg-bubble.bot {
            background: var(--pg-bot-bg); color: var(--pg-bot-text);
            border: 1px solid var(--pg-bot-border); border-bottom-left-radius: 0.3rem;
        }
        .gasai-pg .pg-bubble.user {
            background: #f59e0b; color: #1c1917; border-bottom-right-radius: 0.3rem;
        }
        .gasai-pg .pg-time { font-size: 0.68rem; color: var(--pg-muted); padding: 0 0.35rem; }
        .gasai-pg .pg-row.user .pg-time { text-align: right; }
        .gasai-pg .pg-empty { margin: auto; text-align: center; color: var(--pg-muted); font-size: 0.9rem; }
        .gasai-pg .pg-empty svg { width: 34px; height: 34px; margin: 0 auto 0.5rem; opacity: 0.6; }
        .gasai-pg .pg-dots { display: inline-flex; gap: 4px; align-items: center; }
        .gasai-pg .pg-dots span {
            width: 6px; height: 6px; border-radius: 50%; background: var(--pg-muted);
            animation: pgBounce 1.2s infinite ease-in-out both;
        }
        .gasai-pg .pg-dots span:nth-child(2) { animation-delay: 0.15s; }
        .gasai-pg .pg-dots span:nth-child(3) { animation-delay: 0.3s; }
        @keyframes pgBounce { 0%, 80%, 100% { transform: scale(0.6); opacity: 0.5; } 40% { transform: scale(1); opacity: 1; } }
        @media (prefers-reduced-motion: reduce) { .gasai-pg .pg-dots span { animation: none; } }
        .gasai-pg .pg-debug {
            background: #0b1020; color: #e2e8f0; border-radius: 0.7rem; padding: 0.8rem;
            font-size: 0.72rem; overflow: auto; border: 1px solid #1e293b;
        }
        .gasai-pg .pg-debug .k { color: #fbbf24; font-weight: 700; }
        .gasai-pg .pg-debug .m { color: #94a3b8; }
    </style>

    <div class="gasai-pg" style="max-width: 880px; display: grid; gap: 1rem;"
         x-data x-on:run-agent.window="$wire.runAgent()">

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

        {{-- Hilo de mensajes --}}
        <div x-data="{}"
             x-init="() => { const el = $refs.thread; const b = () => el.scrollTop = el.scrollHeight; b();
                             new MutationObserver(b).observe(el, { childList: true, subtree: true }); }">
            <div class="pg-thread" x-ref="thread">
                @forelse ($thread as $msg)
                    @php($isUser = $msg['role'] === 'user')
                    <div class="pg-row {{ $isUser ? 'user' : 'bot' }}">
                        <div class="pg-av {{ $isUser ? 'user' : 'bot' }}">
                            @if ($isUser)
                                <x-heroicon-s-user />
                            @else
                                <x-heroicon-s-sparkles />
                            @endif
                        </div>
                        <div class="pg-col">
                            <div class="pg-bubble {{ $isUser ? 'user' : 'bot' }}">{{ $msg['content'] }}</div>
                            <span class="pg-time">{{ $msg['time'] }}</span>
                        </div>
                    </div>
                @empty
                    <div class="pg-empty">
                        <x-heroicon-o-chat-bubble-left-right />
                        Escribe un mensaje para conversar con tu agente.<br>
                        Prueba: <em>"Hola, ¿qué venden?"</em>
                    </div>
                @endforelse

                {{-- Indicador de "escribiendo" --}}
                @if ($pending)
                    <div class="pg-row bot">
                        <div class="pg-av bot"><x-heroicon-s-sparkles /></div>
                        <div class="pg-bubble bot">
                            <span class="pg-dots"><span></span><span></span><span></span></span>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Entrada --}}
        <form wire:submit="send" class="flex items-end gap-3">
            <div class="flex-1">
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="input"
                        placeholder="Escribe como si fueras el cliente…"
                        x-bind:disabled="$wire.pending" />
                </x-filament::input.wrapper>
            </div>
            <x-filament::button type="submit" icon="heroicon-o-paper-airplane" x-bind:disabled="$wire.pending">
                <span x-show="! $wire.pending">Enviar</span>
                <span x-show="$wire.pending">Enviando…</span>
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
