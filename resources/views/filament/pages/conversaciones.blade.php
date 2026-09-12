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
        .gx { display: grid; grid-template-columns: 340px 1fr 320px; gap: 1rem;
              height: calc(100vh - 130px); align-items: stretch; transition: grid-template-columns .18s ease; }
        .gx.collapsed { grid-template-columns: 1fr 320px; }
        @media (max-width: 1100px) { .gx, .gx.collapsed { grid-template-columns: 320px 1fr; } .gx .details { display: none; } }
        @media (max-width: 800px) { .gx, .gx.collapsed { grid-template-columns: 1fr; height: auto; min-height: calc(100vh - 130px); } }
        .gx .collapse-btn, .gx .expand-btn { display:flex; align-items:center; justify-content:center; width:30px; height:30px;
            border-radius:.5rem; border:1px solid var(--border); background:var(--elev); color:var(--muted); cursor:pointer; }
        .gx .collapse-btn:hover, .gx .expand-btn:hover { color:var(--text); }
        .gx .details .row { padding: .55rem 0; border-bottom: 1px solid var(--border); }
        .gx .details .lbl { font-size: .7rem; text-transform: uppercase; letter-spacing: .03em; color: var(--muted); }
        .gx .details .val { font-size: .9rem; margin-top: 2px; }
        .gx .chipx { display: inline-block; font-size: .74rem; border-radius: 999px; padding: .1rem .55rem;
            background: var(--elev); border: 1px solid var(--border); margin: 2px 4px 2px 0; }
        .gx .panel { background: var(--surface); border: 1px solid var(--border); border-radius: 1rem; box-shadow: var(--shadow);
            overflow: hidden; display: flex; flex-direction: column; min-height: 0; }
        .gx .details { overflow-y: auto; }
        .gx .muted { color: var(--muted); }

        /* Lista */
        /* Cabecera + pestañas */
        .gx .list-head { padding: .9rem .85rem .6rem; border-bottom: 1px solid var(--border); }
        .gx .lh-title { display:flex; align-items:center; gap:.4rem; font-size:1.05rem; font-weight:800; }
        .gx .tabs { display:flex; gap:.4rem; margin-top:.7rem; background:var(--bg2); padding:.25rem; border-radius:.7rem; }
        .gx .tab { flex:1; display:flex; align-items:center; justify-content:center; gap:.35rem; padding:.4rem .3rem;
            border:0; background:transparent; color:var(--muted); font-size:.82rem; font-weight:600; border-radius:.55rem; cursor:pointer; }
        .gx .tab.active { background:var(--surface); color:var(--text); box-shadow:var(--shadow); }
        .gx .cbadge { font-size:.7rem; background:var(--elev); color:var(--muted); border-radius:999px; padding:0 .4rem; min-width:18px; text-align:center; }
        .gx .tab.active .cbadge { background:#f59e0b; color:#1c1917; }

        .gx .list { display: flex; flex-direction: column; overflow-y: auto; flex:1; }
        .gx .conv { display: flex; gap: .7rem; align-items: center; width: 100%; text-align: left;
            padding: .65rem .85rem; border: 0; border-bottom: 1px solid var(--border); background: transparent;
            color: var(--text); cursor: pointer; transition: background .15s ease; }
        .gx .conv:hover { background: var(--elev); }
        .gx .conv-del { flex:0 0 auto; background:transparent; border:0; color:var(--muted); cursor:pointer; opacity:0;
            padding:5px; border-radius:6px; transition:opacity .12s ease, color .12s ease, background .12s ease; }
        .gx .conv:hover .conv-del { opacity:.65; }
        .gx .conv-del:hover { color:#ef4444; opacity:1; background:rgba(239,68,68,.12); }
        .gx .conv.active { background: var(--elev); box-shadow: inset 3px 0 0 #f59e0b; }
        .gx .av { width: 42px; height: 42px; border-radius: 50%; flex: 0 0 42px; display: flex;
            align-items: center; justify-content: center; background: #fde68a; color: #92400e; font-weight:700; font-size:.85rem; }
        .dark .gx .av { background: #78500a; color: #fde68a; }
        .gx .av svg { width: 20px; height: 20px; }
        .gx .conv-top { display:flex; justify-content:space-between; align-items:baseline; gap:.5rem; }
        .gx .conv .name { font-weight: 600; font-size: .9rem; line-height: 1.1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .gx .time { font-size:.7rem; color:var(--muted); flex:0 0 auto; }
        .gx .conv-bottom { display:flex; justify-content:space-between; align-items:center; gap:.5rem; margin-top:2px; }
        .gx .conv .sub { font-size: .78rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .gx .dot { width: 9px; height: 9px; border-radius: 50%; flex: 0 0 9px; }
        .gx .dot.bot { background: #22c55e; } .gx .dot.human { background: #f59e0b; }

        /* Thread */
        .gx .thread-wrap { display: flex; flex-direction: column; flex: 1; min-height: 0; }
        .gx .thead { display: flex; align-items: center; justify-content: space-between; gap: .75rem;
            padding: .8rem 1rem; border-bottom: 1px solid var(--border); }
        .gx .pill { font-size: .72rem; font-weight: 600; border-radius: 999px; padding: .12rem .55rem; }
        .gx .pill.bot { background: #dcfce7; color: #166534; } .gx .pill.human { background: #fef3c7; color: #92400e; }
        .gx .body { flex: 1; overflow-y: auto; padding: 1.1rem; background: var(--page);
            display: flex; flex-direction: column; gap: .7rem; scroll-behavior: smooth; }
        .gx .bubble { max-width: 74%; padding: .55rem .85rem; border-radius: 1.1rem; font-size: .9rem;
            line-height: 1.4; white-space: pre-wrap; word-break: break-word; box-shadow: var(--shadow); }
        .gx .bubble.in { background: var(--in-bg); color: var(--in-text); border-bottom-left-radius: .3rem; }
        .gx .bubble.out { background: #f59e0b; color: #1c1917; border-bottom-right-radius: .3rem; }
        .gx .msg { display:flex; flex-direction:column; max-width:76%; }
        .gx .msg.in { align-self:flex-start; align-items:flex-start; }
        .gx .msg.out { align-self:flex-end; align-items:flex-end; }
        .gx .btime { font-size:.65rem; color:var(--muted); margin:2px .4rem 0; }
        .gx .daysep { text-align:center; margin:.4rem 0; }
        .gx .daysep span { font-size:.72rem; color:var(--muted); background:var(--elev); padding:.15rem .7rem; border-radius:999px; }
        .gx .empty { margin: auto; text-align: center; color: var(--muted); }
        .gx .empty svg { width: 42px; height: 42px; margin: 0 auto .6rem; opacity: .5; }
        .gx .foot { padding: .8rem 1rem; border-top: 1px solid var(--border); }
    </style>

    <div class="gx {{ $listCollapsed ? 'collapsed' : '' }}">
        {{-- Lista --}}
        <div class="panel" @if($listCollapsed) style="display:none;" @endif>
            @php($cnt = $this->counts())
            <div class="list-head">
                <div class="lh-title" style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="display:flex;align-items:center;gap:.4rem;"><x-heroicon-s-chat-bubble-left-right style="width:20px;height:20px;color:#f59e0b;" /> Conversaciones</span>
                    <button class="collapse-btn" wire:click="toggleList" title="Minimizar lista"><x-heroicon-o-chevron-double-left style="width:16px;height:16px;" /></button>
                </div>
                <div class="tabs">
                    <button class="tab {{ $filter === 'all' ? 'active' : '' }}" wire:click="setFilter('all')">
                        Todos <span class="cbadge">{{ $cnt['all'] }}</span>
                    </button>
                    <button class="tab {{ $filter === 'bot' ? 'active' : '' }}" wire:click="setFilter('bot')">
                        🤖 IA <span class="cbadge">{{ $cnt['bot'] }}</span>
                    </button>
                    <button class="tab {{ $filter === 'humano' ? 'active' : '' }}" wire:click="setFilter('humano')">
                        🧑 Míos <span class="cbadge">{{ $cnt['humano'] }}</span>
                    </button>
                </div>
            </div>
            <div class="list">
                @forelse ($this->conversations() as $c)
                    @php($preview = \Illuminate\Support\Str::limit($c->lastMessage?->content, 38))
                    <div wire:click="select({{ $c->id }})" class="conv {{ $selectedId === $c->id ? 'active' : '' }}" role="button" tabindex="0">
                        <span class="av">{{ \Illuminate\Support\Str::of($c->contactLabel())->substr(0,2)->upper() }}</span>
                        <span style="flex:1; min-width:0;">
                            <div class="conv-top">
                                <span class="name">{{ $c->contactLabel() }}</span>
                                <span class="time">{{ $c->last_activity_at?->diffForHumans(short: true) }}</span>
                            </div>
                            <div class="conv-bottom">
                                <span class="sub muted">{{ $preview ?: $c->channel }}</span>
                                <span class="dot {{ $c->status === 'humano' ? 'human' : 'bot' }}" title="{{ $c->status === 'humano' ? 'Humano' : 'Bot' }}"></span>
                            </div>
                        </span>
                        <button type="button" class="conv-del" title="Eliminar conversación"
                            wire:click.stop="deleteConversation({{ $c->id }})"
                            wire:confirm="¿Eliminar esta conversación? Se ocultará de la bandeja (no se borra el historial).">
                            <x-heroicon-o-trash style="width:15px;height:15px;" />
                        </button>
                    </div>
                @empty
                    <div class="empty" style="padding:2.5rem 1rem;">
                        <x-heroicon-o-inbox />
                        No hay conversaciones en esta pestaña.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Thread --}}
        <div class="panel">
            <div class="thread-wrap">
                @if (! $selected)
                    @if ($listCollapsed)
                        <div class="thead" style="justify-content:flex-start;">
                            <button class="expand-btn" wire:click="toggleList" title="Mostrar lista">
                                <x-heroicon-o-chevron-double-right style="width:16px;height:16px;" />
                            </button>
                            <span class="muted" style="font-size:.85rem;">Lista minimizada</span>
                        </div>
                    @endif
                    <div class="body" style="align-items:center; justify-content:center;">
                        <div class="empty">
                            <x-heroicon-o-chat-bubble-left-right />
                            Selecciona una conversación de la izquierda.
                        </div>
                    </div>
                @else
                    <div class="thead">
                        <div style="display:flex; align-items:center; gap:.6rem;">
                            @if ($listCollapsed)
                                <button class="expand-btn" wire:click="toggleList" title="Mostrar lista">
                                    <x-heroicon-o-chevron-double-right style="width:16px;height:16px;" />
                                </button>
                            @endif
                            <span class="av"><x-heroicon-s-user /></span>
                            <div>
                                <div style="font-weight:600;">{{ $selected->contactLabel() }}</div>
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
                        @php($lastDay = null)
                        @forelse ($this->thread() as $m)
                            @php($day = $m->created_at?->format('Y-m-d'))
                            @if ($day !== $lastDay)
                                <div class="daysep"><span>{{ $m->created_at?->isoFormat('D [de] MMMM') }}</span></div>
                                @php($lastDay = $day)
                            @endif
                            <div class="msg {{ $m->role === 'user' ? 'in' : 'out' }}">
                                <div class="bubble {{ $m->role === 'user' ? 'in' : 'out' }}">{{ $m->content }}</div>
                                <span class="btime">{{ $m->created_at?->format('H:i') }}</span>
                            </div>
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

        {{-- Datos del cliente --}}
        <div class="panel details" style="padding: 1rem;">
            @php($cp = $this->customerPanel())
            @if (! $cp)
                <div class="empty" style="padding:2rem 0;">
                    <x-heroicon-o-identification />
                    Selecciona una conversación para ver los datos del cliente.
                </div>
            @elseif (! $cp['registered'])
                <div style="text-align:center; padding:1rem 0;">
                    <span class="av" style="margin:0 auto .6rem;"><x-heroicon-s-user /></span>
                    <div style="font-weight:600;">{{ $cp['name'] ?? 'Contacto no registrado' }}</div>
                    <div class="muted" style="font-size:.85rem;">{{ $cp['phone'] }}</div>
                    @if ($cp['name'])<div class="muted" style="font-size:.75rem;">(aún no registrado)</div>@endif
                </div>
            @else
                <div style="text-align:center; padding:.25rem 0 .75rem;">
                    <span class="av" style="margin:0 auto .5rem; width:52px; height:52px; flex-basis:52px;">
                        <x-heroicon-s-user />
                    </span>
                    <div style="font-weight:700; font-size:1rem;">{{ $cp['name'] ?? 'Sin nombre' }}</div>
                    <div class="muted" style="font-size:.85rem;">{{ $cp['phone'] }}</div>
                </div>

                @if ($cp['notes'])
                    <div class="row"><div class="lbl">Notas</div><div class="val">{{ $cp['notes'] }}</div></div>
                @endif

                <div class="row">
                    <div class="lbl">Direcciones</div>
                    @forelse ($cp['addresses'] as $a)
                        <div class="val" style="margin-top:6px;">
                            <x-heroicon-s-map-pin style="width:14px;height:14px;display:inline;vertical-align:-2px;color:#f59e0b;" />
                            {{ $a->address }}
                            @if ($a->is_primary)<span class="chipx">principal</span>@endif
                            @if ($a->reference)<div class="muted" style="font-size:.78rem;">Ref: {{ $a->reference }}</div>@endif
                        </div>
                    @empty
                        <div class="muted val">Sin direcciones guardadas.</div>
                    @endforelse
                </div>

                @if ($cp['envases']->isNotEmpty())
                    <div class="row">
                        <div class="lbl">Envases en su poder</div>
                        <div class="val" style="margin-top:4px;">
                            @foreach ($cp['envases'] as $e)
                                <span class="chipx">{{ $e->containerType?->name ?? 'Envase' }}: <strong>{{ $e->balance }}</strong></span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="row" style="border-bottom:0;">
                    <div class="lbl">Pedidos</div>
                    <div class="val">
                        {{ $cp['orders_count'] }} en total
                        @if ($cp['last_order'])
                            <div class="muted" style="font-size:.8rem; margin-top:2px;">
                                Último: #{{ $cp['last_order']->id }} · {{ \App\Models\Order::LABELS[$cp['last_order']->status] ?? $cp['last_order']->status }} · S/ {{ number_format((float) $cp['last_order']->total, 2) }}
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
