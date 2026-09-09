<x-filament-panels::page>
    @php($selected = $this->selected())

    <style>
        .gasai-inbox {
            --bg: #ffffff; --bg2: #f8fafc; --text: #0f172a; --muted: #64748b;
            --border: #e5e7eb; --sel: rgba(245,158,11,0.14);
            --bot-bg: #ffffff; --bot-text: #0f172a; --bot-border: #e5e7eb;
        }
        .dark .gasai-inbox {
            --bg: #1f2937; --bg2: #111827; --text: #f3f4f6; --muted: #9ca3af;
            --border: #374151; --sel: rgba(245,158,11,0.20);
            --bot-bg: #374151; --bot-text: #f3f4f6; --bot-border: #4b5563;
        }
        .gasai-inbox .panel { border: 1px solid var(--border); border-radius: 0.85rem; background: var(--bg); overflow: hidden; }
        .gasai-inbox .conv-item { display: block; width: 100%; text-align: left; padding: 0.75rem;
            border-bottom: 1px solid var(--border); color: var(--text); background: transparent; }
        .gasai-inbox .conv-item.active { background: var(--sel); }
        .gasai-inbox .conv-item:hover { background: var(--sel); }
        .gasai-inbox .muted { color: var(--muted); }
        .gasai-inbox .badge { font-size: 0.7rem; border-radius: 999px; padding: 0.1rem 0.5rem; font-weight: 600; }
        .gasai-inbox .badge.bot { background: #dcfce7; color: #166534; }
        .gasai-inbox .badge.human { background: #fee2e2; color: #991b1b; }
        .gasai-inbox .thread { flex: 1; padding: 0.9rem; overflow-y: auto; height: 46vh; min-height: 300px;
            display: flex; flex-direction: column; gap: 0.5rem; background: var(--bg2); }
        .gasai-inbox .bubble { max-width: 76%; padding: 0.5rem 0.8rem; border-radius: 1rem;
            font-size: 0.88rem; line-height: 1.35; white-space: pre-wrap; word-break: break-word; }
        .gasai-inbox .bubble.in { background: var(--bot-bg); color: var(--bot-text); border: 1px solid var(--bot-border);
            border-bottom-left-radius: 0.25rem; align-self: flex-start; }
        .gasai-inbox .bubble.out { background: #f59e0b; color: #1c1917; border-bottom-right-radius: 0.25rem; align-self: flex-end; }
    </style>

    <div class="gasai-inbox" style="display:grid;grid-template-columns:300px 1fr;gap:1rem;min-height:480px;">

        {{-- Lista de conversaciones --}}
        <div class="panel">
            @forelse ($this->conversations() as $c)
                <button wire:click="select({{ $c->id }})" class="conv-item {{ $selectedId === $c->id ? 'active' : '' }}">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium text-sm">{{ $c->customer?->name ?? $c->phone ?? 'Anónimo' }}</span>
                        <span class="badge {{ $c->status === 'humano' ? 'human' : 'bot' }}">
                            {{ $c->status === 'humano' ? 'Humano' : 'Bot' }}
                        </span>
                    </div>
                    <div class="text-xs muted mt-0.5">{{ $c->channel }} · {{ $c->last_activity_at?->diffForHumans() }}</div>
                </button>
            @empty
                <p class="text-sm muted p-4">No hay conversaciones todavía.</p>
            @endforelse
        </div>

        {{-- Hilo seleccionado --}}
        <div class="panel" style="display:flex;flex-direction:column;">
            @if (! $selected)
                <div class="flex-1 flex items-center justify-center text-sm muted" style="min-height:300px;">
                    Selecciona una conversación de la izquierda.
                </div>
            @else
                <div class="flex items-center justify-between p-3" style="border-bottom:1px solid var(--border);">
                    <div>
                        <div class="font-semibold">{{ $selected->customer?->name ?? $selected->phone ?? 'Anónimo' }}</div>
                        <div class="text-xs muted">
                            {{ $selected->status === 'humano' ? 'Atendido por humano' : 'Atendido por el bot' }}
                        </div>
                    </div>
                    <div class="flex gap-2">
                        @if ($selected->status === 'humano')
                            <x-filament::button size="xs" color="gray" wire:click="returnToBot">Devolver al agente</x-filament::button>
                        @else
                            <x-filament::button size="xs" wire:click="takeControl">Tomar control</x-filament::button>
                        @endif
                    </div>
                </div>

                <div class="thread"
                     x-data="{}"
                     x-init="() => { const el = $el; const b = () => el.scrollTop = el.scrollHeight; b(); new MutationObserver(b).observe(el, {childList:true, subtree:true}); }">
                    @foreach ($this->thread() as $m)
                        <div class="bubble {{ $m->role === 'user' ? 'in' : 'out' }}">{{ $m->content }}</div>
                    @endforeach
                </div>

                <div class="p-3" style="border-top:1px solid var(--border);">
                    @if ($selected->status === 'humano')
                        <form wire:submit="sendMessage" class="flex gap-2 items-end">
                            <div class="flex-1">
                                <x-filament::input.wrapper>
                                    <x-filament::input type="text" wire:model="draft" placeholder="Escribe tu respuesta…" />
                                </x-filament::input.wrapper>
                            </div>
                            <x-filament::button type="submit" icon="heroicon-o-paper-airplane">Enviar</x-filament::button>
                        </form>
                    @else
                        <p class="text-xs muted">Toma el control para responder manualmente al cliente.</p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
