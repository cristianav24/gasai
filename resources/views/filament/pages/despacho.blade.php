<x-filament-panels::page>
    @php($grupos = $this->ordersByStatus())
    @php($couriers = $this->couriers())
    @php($accents = ['pendiente' => '#f59e0b', 'confirmado' => '#0ea5e9', 'en_ruta' => '#8b5cf6', 'entregado' => '#22c55e'])

    <style>
        .gb {
            --bg: #f1f5f9; --col: #f8fafc; --card: #ffffff; --text: #0f172a; --muted: #64748b;
            --border: #e5e7eb; --chip: #eef2f7; --input: #ffffff; --shadow: 0 1px 2px rgba(15,23,42,.08);
            --overlay: rgba(15,23,42,.45);
        }
        .dark .gb {
            --bg: #0b1220; --col: #111c2e; --card: #1e293b; --text: #f3f4f6; --muted: #94a3b8;
            --border: #2b3a54; --chip: #23324b; --input: #0f1b30; --shadow: 0 1px 2px rgba(0,0,0,.4);
            --overlay: rgba(2,6,23,.65);
        }

        /* Barra superior */
        .gb .gb-bar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .gb .gb-hint { color: var(--muted); font-size: .85rem; }
        .gb .gb-new {
            display: inline-flex; align-items: center; gap: .5rem; padding: .6rem 1rem; border: 0; cursor: pointer;
            border-radius: .7rem; font-weight: 700; font-size: .9rem; color: #1c1917;
            background: linear-gradient(135deg, #f59e0b, #f97316); box-shadow: 0 4px 12px rgba(245,158,11,.35);
        }
        .gb .gb-new:hover { filter: brightness(1.05); }
        .gb .gb-new svg { width: 18px; height: 18px; }

        /* Tablero */
        .gb .board { display: grid; grid-template-columns: repeat(4, minmax(250px, 1fr)); gap: 1rem; align-items: start; }
        @media (max-width: 1000px) { .gb .board { grid-auto-flow: column; grid-template-columns: none; grid-auto-columns: 82vw; overflow-x: auto; } }
        .gb .col { background: var(--col); border: 1px solid var(--border); border-radius: 1rem; padding: .8rem; }
        .gb .col-head { display: flex; align-items: center; gap: .5rem; margin-bottom: .8rem; padding: 0 .15rem; }
        .gb .col-dot { width: 9px; height: 9px; border-radius: 50%; }
        .gb .col-title { font-weight: 700; font-size: .9rem; color: var(--text); }
        .gb .col-count { margin-left: auto; font-size: .74rem; font-weight: 700; color: var(--muted);
            background: var(--chip); border-radius: 999px; padding: .1rem .5rem; min-width: 22px; text-align: center; }
        .gb .col-list { display: flex; flex-direction: column; gap: .7rem; }

        /* Tarjeta de pedido */
        .gb .order {
            position: relative; background: var(--card); border: 1px solid var(--border);
            border-radius: .85rem; padding: .75rem .8rem .75rem 1rem; box-shadow: var(--shadow); overflow: hidden;
        }
        .gb .order::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--accent); }
        .gb .o-top { display: flex; align-items: baseline; justify-content: space-between; gap: .5rem; }
        .gb .o-id { font-weight: 800; font-size: .92rem; color: var(--text); }
        .gb .o-total { font-weight: 800; font-size: .95rem; color: var(--text); }
        .gb .o-cust { display: flex; align-items: center; gap: .5rem; margin-top: .5rem; }
        .gb .o-av { width: 28px; height: 28px; border-radius: 50%; flex: 0 0 28px; display: flex; align-items: center;
            justify-content: center; font-size: .72rem; font-weight: 800; background: #fde68a; color: #92400e; }
        .dark .gb .o-av { background: #78500a; color: #fde68a; }
        .gb .o-name { font-size: .88rem; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .gb .o-meta { display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .55rem; }
        .gb .o-chip { display: inline-flex; align-items: center; gap: .25rem; font-size: .72rem; color: var(--muted);
            background: var(--chip); border-radius: 999px; padding: .12rem .5rem; }
        .gb .o-chip svg { width: 12px; height: 12px; }
        .gb .o-chip.manual { color: #b45309; background: rgba(245,158,11,.15); }
        .dark .gb .o-chip.manual { color: #fcd34d; background: rgba(245,158,11,.12); }
        .gb .o-select { width: 100%; margin-top: .6rem; font-size: .78rem; padding: .35rem .5rem; border-radius: .5rem;
            background: var(--input); color: var(--text); border: 1px solid var(--border); }
        .gb .o-actions { display: flex; gap: .4rem; margin-top: .6rem; }
        .gb .o-btn { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: .25rem;
            font-size: .78rem; font-weight: 700; padding: .4rem .5rem; border-radius: .55rem; border: 0; cursor: pointer; color: #fff; }
        .gb .o-btn.ghost { flex: 0 0 auto; background: transparent; color: var(--muted); border: 1px solid var(--border); }
        .gb .o-btn.ghost:hover { color: #ef4444; border-color: #ef4444; }
        .gb .o-cobrar { display: inline-flex; align-items: center; gap: .3rem; margin-top: .6rem; font-size: .78rem;
            font-weight: 700; color: #16a34a; text-decoration: none; }
        .gb .o-cobrar:hover { text-decoration: underline; }
        .gb .o-cobrar svg { width: 14px; height: 14px; }
        .gb .col-empty { text-align: center; color: var(--muted); font-size: .8rem; padding: 1.4rem 0; }
        .gb .col-empty svg { width: 26px; height: 26px; margin: 0 auto .4rem; opacity: .5; }

        /* Modal nuevo pedido */
        .gb .ov { position: fixed; inset: 0; background: var(--overlay); backdrop-filter: blur(2px); z-index: 50;
            display: flex; align-items: flex-start; justify-content: center; padding: 4vh 1rem; overflow-y: auto; }
        .gb .modal { width: 100%; max-width: 560px; background: var(--card); color: var(--text);
            border: 1px solid var(--border); border-radius: 1.1rem; box-shadow: 0 24px 60px -20px rgba(0,0,0,.5); overflow: hidden; }
        .gb .m-head { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.1rem;
            border-bottom: 1px solid var(--border); }
        .gb .m-title { font-weight: 800; font-size: 1.05rem; }
        .gb .m-close { background: transparent; border: 0; color: var(--muted); cursor: pointer; }
        .gb .m-close:hover { color: var(--text); }
        .gb .m-close svg { width: 20px; height: 20px; }
        .gb .m-body { padding: 1.1rem; display: flex; flex-direction: column; gap: 1rem; }
        .gb .m-foot { padding: .9rem 1.1rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: .6rem; }
        .gb .fld-label { display: block; font-size: .78rem; font-weight: 700; margin-bottom: .35rem; color: var(--text); }
        .gb .fld { width: 100%; padding: .55rem .7rem; border-radius: .6rem; font-size: .9rem;
            background: var(--input); border: 1px solid var(--border); color: var(--text); }
        .gb .fld:focus { outline: none; border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,.15); }
        .gb .seg { display: inline-flex; background: var(--chip); border-radius: .6rem; padding: .2rem; gap: .2rem; }
        .gb .seg button { border: 0; background: transparent; color: var(--muted); font-size: .82rem; font-weight: 700;
            padding: .3rem .7rem; border-radius: .45rem; cursor: pointer; }
        .gb .seg button.on { background: var(--card); color: var(--text); box-shadow: var(--shadow); }
        .gb .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: .7rem; }
        .gb .item-row { display: flex; gap: .5rem; align-items: center; }
        .gb .item-row .fld.prod { flex: 1; }
        .gb .item-row .fld.qty { width: 68px; flex: 0 0 68px; text-align: center; }
        .gb .item-del { background: transparent; border: 1px solid var(--border); color: var(--muted); cursor: pointer;
            border-radius: .5rem; width: 34px; height: 34px; flex: 0 0 34px; display: flex; align-items: center; justify-content: center; }
        .gb .item-del:hover { color: #ef4444; border-color: #ef4444; }
        .gb .item-del svg { width: 16px; height: 16px; }
        .gb .add-item { display: inline-flex; align-items: center; gap: .35rem; margin-top: .5rem; font-size: .82rem;
            font-weight: 700; color: #f59e0b; background: transparent; border: 0; cursor: pointer; padding: 0; }
        .gb .add-item svg { width: 15px; height: 15px; }
        .gb .totals { background: var(--chip); border-radius: .7rem; padding: .7rem .8rem; font-size: .86rem; }
        .gb .totals .row { display: flex; justify-content: space-between; padding: .1rem 0; color: var(--muted); }
        .gb .totals .row.grand { color: var(--text); font-weight: 800; font-size: 1rem; border-top: 1px solid var(--border);
            margin-top: .35rem; padding-top: .5rem; }
        .gb .btn-primary { display: inline-flex; align-items: center; gap: .4rem; padding: .6rem 1.1rem; border: 0; cursor: pointer;
            border-radius: .7rem; font-weight: 700; font-size: .9rem; color: #1c1917;
            background: linear-gradient(135deg, #f59e0b, #f97316); box-shadow: 0 4px 12px rgba(245,158,11,.35); }
        .gb .btn-ghost { padding: .6rem 1rem; border-radius: .7rem; font-weight: 700; font-size: .9rem; cursor: pointer;
            background: transparent; color: var(--muted); border: 1px solid var(--border); }

        /* Acción principal de entrega/cobro y secundarias */
        .gb .o-deliver { display: flex; align-items: center; justify-content: center; gap: .35rem; width: 100%;
            margin-top: .6rem; padding: .5rem; border: 0; cursor: pointer; border-radius: .6rem; font-weight: 800;
            font-size: .82rem; color: #fff; text-decoration: none;
            background: linear-gradient(135deg, #16a34a, #22c55e); box-shadow: 0 3px 10px rgba(22,163,74,.3); }
        .gb .o-deliver.alt { background: linear-gradient(135deg, #0ea5e9, #06b6d4); box-shadow: 0 3px 10px rgba(6,182,212,.3); }
        .gb .o-deliver:hover { filter: brightness(1.05); }
        .gb .o-deliver svg { width: 15px; height: 15px; }
        .gb .o-sub { display: flex; gap: .35rem; margin-top: .5rem; flex-wrap: wrap; }
        .gb .o-mini { display: inline-flex; align-items: center; gap: .25rem; font-size: .72rem; font-weight: 700;
            padding: .28rem .5rem; border-radius: .45rem; border: 1px solid var(--border); background: transparent;
            color: var(--muted); cursor: pointer; }
        .gb .o-mini:hover { color: var(--text); }
        .gb .o-mini.danger:hover { color: #ef4444; border-color: #ef4444; }
        .gb .o-mini svg { width: 12px; height: 12px; }

        /* Precio por línea (modal nuevo pedido) */
        .gb .item-row .fld.price { width: 84px; flex: 0 0 84px; text-align: right; }
        .gb .item-hint { font-size: .72rem; color: var(--muted); margin-top: .1rem; }

        /* Modal editar precios */
        .gb .edit-row { display: flex; align-items: center; gap: .7rem; padding: .55rem 0; border-bottom: 1px solid var(--border); }
        .gb .er-name { font-weight: 700; font-size: .9rem; }
        .gb .er-sub { font-size: .74rem; }
        .gb .edit-row .fld.price { width: 104px; flex: 0 0 104px; text-align: right; }
    </style>

    <div class="gb">
        {{-- Barra: crear pedido manual --}}
        <div class="gb-bar">
            <span class="gb-hint">¿Un pedido por teléfono o mostrador? Regístralo a mano y cae en <strong>Pendiente</strong>.</span>
            <button type="button" class="gb-new" wire:click="openNewOrder">
                <x-heroicon-o-plus /> Nuevo pedido
            </button>
        </div>

        {{-- Tablero --}}
        <div class="board">
            @foreach ($this->columns() as $status)
                @php($pedidos = $grupos[$status] ?? collect())
                @php($accent = $accents[$status] ?? '#64748b')
                <div class="col">
                    <div class="col-head">
                        <span class="col-dot" style="background: {{ $accent }};"></span>
                        <span class="col-title">{{ $this->label($status) }}</span>
                        <span class="col-count">{{ $pedidos->count() }}</span>
                    </div>

                    <div class="col-list">
                        @forelse ($pedidos as $order)
                            <div class="order" style="--accent: {{ $accent }};">
                                <div class="o-top">
                                    <span class="o-id">#{{ $order->id }}</span>
                                    <span class="o-total">S/ {{ number_format((float) $order->total, 2) }}</span>
                                </div>

                                <div class="o-cust">
                                    <span class="o-av">{{ \Illuminate\Support\Str::of($order->customer?->displayName() ?? 'S')->substr(0, 2)->upper() }}</span>
                                    <span class="o-name">{{ $order->customer?->displayName() ?? 'Sin cliente' }}</span>
                                </div>

                                <div class="o-meta">
                                    <span class="o-chip">
                                        <x-heroicon-o-calendar-days />
                                        {{ $order->scheduled_date?->format('d/m') ?? '—' }}
                                        · {{ \App\Models\Order::LABELS[$order->scheduled_slot] ?? ($order->scheduled_slot ?? '—') }}
                                        @if ($order->scheduled_time) · {{ \Illuminate\Support\Str::of($order->scheduled_time)->substr(0, 5) }} @endif
                                    </span>
                                    <span class="o-chip"><x-heroicon-o-cube />{{ $order->items->sum('quantity') }} ítem(s)</span>
                                    @if ($order->address?->mapUrl())
                                        <a class="o-chip" style="text-decoration:none;color:#0891a6;" href="{{ $order->address->mapUrl() }}" target="_blank" rel="noopener"><x-heroicon-o-map-pin />Mapa</a>
                                    @endif
                                    @if ($order->channel === 'manual')
                                        <span class="o-chip manual"><x-heroicon-o-pencil-square />Manual</span>
                                    @elseif ($order->channel === 'whatsapp')
                                        <span class="o-chip"><x-heroicon-o-chat-bubble-oval-left />WhatsApp</span>
                                    @endif
                                </div>

                                {{-- Repartidor --}}
                                <select class="o-select" wire:change="assignCourier({{ $order->id }}, $event.target.value)">
                                    <option value="">Sin repartidor</option>
                                    @foreach ($couriers as $courier)
                                        <option value="{{ $courier->id }}" @selected($order->courier_id === $courier->id)>{{ $courier->name }}</option>
                                    @endforeach
                                </select>

                                {{-- Acción principal: entregar y cobrar (o cobrar si ya se entregó) --}}
                                @if ($status !== 'entregado')
                                    <button class="o-deliver" wire:click="deliverAndCharge({{ $order->id }})">
                                        <x-heroicon-o-banknotes /> Entregar y cobrar
                                    </button>
                                @else
                                    <a href="{{ $this->cobrarUrl($order->id) }}" class="o-deliver alt">
                                        <x-heroicon-o-banknotes /> Cobrar en POS
                                    </a>
                                @endif

                                {{-- Secundarias --}}
                                <div class="o-sub">
                                    @if ($order->nextStatus() && $status !== 'entregado')
                                        <button class="o-mini" wire:click="advance({{ $order->id }})"
                                            style="color: {{ $accents[$order->nextStatus()] ?? '#64748b' }};"
                                            title="Avanzar un paso sin cobrar">
                                            <x-heroicon-s-arrow-right />{{ $this->label($order->nextStatus()) }}
                                        </button>
                                    @endif
                                    <button class="o-mini" wire:click="openEdit({{ $order->id }})" title="Editar precios">
                                        <x-heroicon-o-pencil-square />Precios
                                    </button>
                                    <button class="o-mini danger" wire:click="cancel({{ $order->id }})" title="Cancelar pedido">
                                        <x-heroicon-o-x-mark />
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="col-empty">
                                <x-heroicon-o-inbox />
                                Sin pedidos.
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Modal: nuevo pedido manual --}}
        @if ($showNewOrder)
            @php($calc = $this->newOrderCalc())
            <div class="ov" wire:key="new-order-modal">
                <div class="modal">
                    <div class="m-head">
                        <span class="m-title">Nuevo pedido manual</span>
                        <button class="m-close" wire:click="closeNewOrder"><x-heroicon-o-x-mark /></button>
                    </div>

                    <div class="m-body">
                        {{-- Cliente --}}
                        <div>
                            <label class="fld-label">Cliente</label>
                            <div class="seg" style="margin-bottom:.55rem;">
                                <button type="button" class="{{ $customerMode === 'nuevo' ? 'on' : '' }}" wire:click="$set('customerMode', 'nuevo')">Nuevo</button>
                                <button type="button" class="{{ $customerMode === 'existente' ? 'on' : '' }}" wire:click="$set('customerMode', 'existente')">Existente</button>
                            </div>

                            @if ($customerMode === 'nuevo')
                                <div class="grid2">
                                    <input type="text" class="fld" placeholder="Nombre" wire:model="noName" />
                                    <input type="text" class="fld" placeholder="Teléfono (opcional)" wire:model="noPhone" />
                                </div>
                            @else
                                <select class="fld" wire:model="noCustomerId">
                                    <option value="">Elige un cliente…</option>
                                    @foreach ($this->customersList() as $c)
                                        <option value="{{ $c->id }}">{{ $c->displayName() }}@if($c->phone) · {{ $c->phone }}@endif</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        {{-- Productos --}}
                        <div>
                            <label class="fld-label">Productos</label>
                            @php($prodList = $this->productsList())
                            @php($prodPrices = $prodList->pluck('price', 'id'))
                            @foreach ($noItems as $i => $line)
                                <div class="item-row" wire:key="it-{{ $i }}" style="margin-bottom:.5rem;">
                                    <select class="fld prod" wire:model.live="noItems.{{ $i }}.product_id">
                                        <option value="">Producto…</option>
                                        @foreach ($prodList as $p)
                                            <option value="{{ $p->id }}">{{ $p->name }} · S/ {{ number_format((float) $p->price, 2) }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" min="1" class="fld qty" wire:model.live="noItems.{{ $i }}.qty" title="Cantidad" />
                                    <input type="number" step="0.01" min="0" class="fld price" wire:model.live="noItems.{{ $i }}.price"
                                        title="Precio vendido"
                                        placeholder="{{ ! empty($line['product_id']) && isset($prodPrices[$line['product_id']]) ? number_format((float) $prodPrices[$line['product_id']], 2, '.', '') : 'Precio' }}" />
                                    <button type="button" class="item-del" wire:click="removeOrderItem({{ $i }})"><x-heroicon-o-trash /></button>
                                </div>
                            @endforeach
                            <p class="item-hint">Deja el precio vacío para usar el de lista; cámbialo si lo vendiste a otro valor.</p>
                            <button type="button" class="add-item" wire:click="addOrderItem"><x-heroicon-o-plus /> Agregar producto</button>
                        </div>

                        {{-- Entrega --}}
                        <div class="grid2">
                            <div>
                                <label class="fld-label">Zona de entrega</label>
                                <select class="fld" wire:model.live="noZoneId">
                                    <option value="">Sin zona / retiro</option>
                                    @foreach ($this->zonesList() as $z)
                                        <option value="{{ $z->id }}">{{ $z->name }} · S/ {{ number_format((float) $z->delivery_fee, 2) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="fld-label">Fecha</label>
                                <input type="date" class="fld" wire:model="noDate" />
                            </div>
                        </div>

                        <div class="grid2">
                            <div>
                                <label class="fld-label">Franja</label>
                                <select class="fld" wire:model.live="noSlot">
                                    @foreach (self::SLOTS as $val => $lbl)
                                        <option value="{{ $val }}">{{ $lbl }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="fld-label">Hora {{ $noSlot === 'hora_exacta' ? '' : '(opcional)' }}</label>
                                <input type="time" class="fld" wire:model="noTime" @if($noSlot !== 'hora_exacta') style="opacity:.6;" @endif />
                            </div>
                        </div>

                        {{-- Dirección / notas --}}
                        <div>
                            <label class="fld-label">Dirección y notas</label>
                            <textarea class="fld" rows="2" placeholder="Ej: Jr. Gonzales Prada 753, El Tambo. Tocar el timbre azul."
                                wire:model="noNotes"></textarea>
                        </div>

                        {{-- Totales --}}
                        <div class="totals">
                            <div class="row"><span>Subtotal</span><span>S/ {{ number_format((float) $calc['subtotal'], 2) }}</span></div>
                            <div class="row"><span>Envío</span><span>S/ {{ number_format((float) $calc['costo_envio'], 2) }}</span></div>
                            <div class="row grand"><span>Total</span><span>S/ {{ number_format((float) $calc['total'], 2) }}</span></div>
                        </div>
                    </div>

                    <div class="m-foot">
                        <button type="button" class="btn-ghost" wire:click="closeNewOrder">Cancelar</button>
                        <button type="button" class="btn-primary" wire:click="createOrder">
                            <x-heroicon-s-check style="width:16px;height:16px;" /> Crear pedido
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Modal: editar precios de un pedido existente --}}
        @if ($showEdit)
            <div class="ov" wire:key="edit-order-modal">
                <div class="modal" style="max-width:440px;">
                    <div class="m-head">
                        <span class="m-title">Editar precios · #{{ $editOrderId }}</span>
                        <button class="m-close" wire:click="closeEdit"><x-heroicon-o-x-mark /></button>
                    </div>

                    <div class="m-body">
                        @forelse ($editItems as $i => $row)
                            <div class="edit-row" wire:key="ed-{{ $i }}">
                                <div style="flex:1; min-width:0;">
                                    <div class="er-name">{{ $row['name'] }}</div>
                                    <div class="er-sub muted">Lista: S/ {{ number_format((float) $row['list'], 2) }} · x{{ $row['qty'] }}</div>
                                </div>
                                <input type="number" step="0.01" min="0" class="fld price"
                                    wire:model.live="editItems.{{ $i }}.charged" title="Precio vendido" />
                            </div>
                        @empty
                            <p class="item-hint">Este pedido no tiene líneas.</p>
                        @endforelse

                        <div class="totals" style="margin-top:.9rem;">
                            <div class="row grand"><span>Total</span><span>S/ {{ number_format($this->editTotal(), 2) }}</span></div>
                        </div>
                    </div>

                    <div class="m-foot">
                        <button type="button" class="btn-ghost" wire:click="closeEdit">Cancelar</button>
                        <button type="button" class="btn-primary" wire:click="saveEdit">
                            <x-heroicon-s-check style="width:16px;height:16px;" /> Guardar precios
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
