<x-filament-panels::page>
    @php($selected = $this->selected())

    <style>
        .gx {
            --page: #f8fafc; --surface: #ffffff; --elev: #f1f5f9; --text: #0f172a;
            --muted: #64748b; --border: #e2e8f0; --in-bg: #f1f5f9; --in-text: #0f172a;
            --shadow: 0 1px 3px rgba(15,23,42,.08), 0 1px 2px rgba(15,23,42,.04);
        }
        .dark .gx {
            --page: #0b1220; --surface: #1e293b; --elev: #273449; --text: #f1f5f9;
            --muted: #94a3b8; --border: #334155; --in-bg: #273449; --in-text: #f1f5f9;
            --shadow: 0 1px 3px rgba(0,0,0,.5);
        }
        .gx { display: grid; grid-template-columns: 320px 1fr; gap: 1rem; }
        @media (max-width: 800px) { .gx { grid-template-columns: 1fr; } }
        .gx .panel { background: var(--surface); border: 1px solid var(--border); border-radius: 1rem; box-shadow: var(--shadow); overflow: hidden; }
        .gx .muted { color: var(--muted); }

        /* Lista */
        .gx .list { display: flex; flex-direction: column; max-height: 62vh; overflow-y: auto; }
        .gx .conv { display: flex; gap: .7rem; align-items: center; width: 100%; text-align: left;
            padding: .7rem .85rem; border: 0; border-bottom: 1px solid var(--border); background: transparent;
            color: var(--text); cursor: pointer; transition: background .15s ease; }
        .gx .conv:hover { background: var(--elev); }
        .gx .conv.active { background: var(--elev); box-shadow: inset 3px 0 0 #f59e0b; }
        .gx .av { width: 40px; height: 40px; border-radius: 50%; flex: 0 0 40px; display: flex;
            align-items: center; justify-content: center; background: #fde68a; color: #92400e; }
        .dark .gx .av { background: #78500a; color: #fde68a; }
        .gx .av svg { width: 20px; height: 20px; }
        .gx .conv .name { font-weight: 600; font-size: .9rem; line-height: 1.1; }
        .gx .conv .sub { font-size: .74rem; }
        .gx .dot { width: 8px; height: 8px; border-radius: 50%; flex: 0 0 8px; }
        .gx .dot.bot { background: #22c55e; } .gx .dot.human { background: #f59e0b; }

        /* Thread */
        .gx .thread-wrap { display: flex; flex-direction: column; height: 66vh; }
        .gx .thead { display: flex; align-items: center; justify-content: space-between; gap: .75rem;
            padding: .8rem 1rem; border-bottom: 1px solid var(--border); }
        .gx .pill { font-size: .72rem; font-weight: 600; border-radius: 999px; padding: .12rem .55rem; }
        .gx .pill.bot { background: #dcfce7; color: #166534; } .gx .pill.human { background: #fef3c7; color: #92400e; }
        .gx .body { flex: 1; overflow-y: auto; padding: 1.1rem; background: var(--page);
            display: flex; flex-direction: column; gap: .7rem; scroll-behavior: smooth; }
        .gx .bubble { max-width: 74%; padding: .55rem .85rem; border-radius: 1.1rem; font-size: .9rem;
            line-height: 1.4; white-space: pre-wrap; word-break: break-word; box-shadow: var(--shadow); }
        .gx .bubble.in { background: var(--in-bg); color: var(--in-text); align-self: flex-start; border-bottom-left-radius: .3rem; }
        .gx .bubble.out { background: #f59e0b; color: #1c1917; align-self: flex-end; border-bottom-right-radius: .3rem; }
        .gx .empty { margin: auto; text-align: center; color: var(--muted); }
        .gx .empty svg { width: 42px; height: 42px; margin: 0 auto .6rem; opacity: .5; }
        .gx .foot { padding: .8rem 1rem; border-top: 1px solid var(--border); }
    </style>

    <div class="gx">
        {{-- Lista --}}
        <div class="panel">
            <div class="list">
                @forelse ($this->conversations() as $c)
                    <button wire:click="select({{ $c->id }})" class="conv {{ $selectedId === $c->id ? 'active' : '' }}">
                        <span class="av"><x-heroicon-s-user /></span>
                        <span style="flex:1; min-width:0;">
                            <span class="name">{{ $c->customer?->name ?? $c->phone ?? 'Anónimo' }}</span>
                            <div class="sub muted">{{ $c->channel }} · {{ $c->last_activity_at?->diffForHumans() }}</div>
                        </span>
                        <span class="dot {{ $c->status === 'humano' ? 'human' : 'bot' }}" title="{{ $c->status === 'humano' ? 'Humano' : 'Bot' }}"></span>
                    </button>
                @empty
                    <div class="empty" style="padding:2.5rem 1rem;">
                        <x-heroicon-o-inbox />
                        No hay conversaciones todavía.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Thread --}}
        <div class="panel">
            <div class="thread-wrap">
                @if (! $selected)
                    <div class="body" style="align-items:center; justify-content:center;">
                        <div class="empty">
                            <x-heroicon-o-chat-bubble-left-right />
                            Selecciona una conversación de la izquierda.
                        </div>
                    </div>
                @else
                    <div class="thead">
                        <div style="display:flex; align-items:center; gap:.6rem;">
                            <span class="av"><x-heroicon-s-user /></span>
                            <div>
                                <div style="font-weight:600;">{{ $selected->customer?->name ?? $selected->phone ?? 'Anónimo' }}</div>
                                <div class="muted" style="font-size:.75rem;">
                                    <span class="pill {{ $selected->status === 'humano' ? 'human' : 'bot' }}">
                                        {{ $selected->status === 'humano' ? 'Atendido por humano' : 'Atendido por el bot' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            @if ($selected->status === 'humano')
                                <x-filament::button size="xs" color="gray" icon="heroicon-o-arrow-uturn-left" wire:click="returnToBot">Devolver al agente</x-filament::button>
                            @else
                                <x-filament::button size="xs" icon="heroicon-o-hand-raised" wire:click="takeControl">Tomar control</x-filament::button>
                            @endif
                        </div>
                    </div>

                    <div class="body" x-data="{}"
                         x-init="() => { const el=$el; const b=()=>el.scrollTop=el.scrollHeight; b();
                                         new MutationObserver(b).observe(el,{childList:true,subtree:true}); }">
                        @forelse ($this->thread() as $m)
                            <div class="bubble {{ $m->role === 'user' ? 'in' : 'out' }}">{{ $m->content }}</div>
                        @empty
                            <div class="empty" style="margin:auto;"><x-heroicon-o-chat-bubble-oval-left />Sin mensajes aún.</div>
                        @endforelse
                    </div>

                    <div class="foot">
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
    </div>
</x-filament-panels::page>
