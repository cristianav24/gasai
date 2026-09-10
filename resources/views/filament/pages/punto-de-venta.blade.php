<x-filament-panels::page>
    <style>
        .pos { --bg:#f8fafc; --card:#ffffff; --text:#0f172a; --muted:#64748b; --border:#e2e8f0;
               --accent:#0ea5e9; --shadow:0 1px 3px rgba(15,23,42,.08); }
        .dark .pos { --bg:#0b1220; --card:#1e293b; --text:#f1f5f9; --muted:#94a3b8; --border:#334155;
                     --accent:#38bdf8; --shadow:0 1px 3px rgba(0,0,0,.5); }
        .pos { display:grid; grid-template-columns: 1fr 440px; gap:1rem; color:var(--text); }
        @media (max-width:1024px){ .pos{ grid-template-columns:1fr; } }
        .pos .panel{ background:var(--card); border:1px solid var(--border); border-radius:1rem; box-shadow:var(--shadow); }
        .pos .muted{ color:var(--muted); }
        .pos select, .pos input[type=text], .pos input[type=number]{
            background:var(--card); color:var(--text); border:1px solid var(--border); border-radius:.6rem; padding:.5rem .7rem; }

        /* ---- Ticket (izquierda) ---- */
        .pos .ticket{ display:flex; flex-direction:column; min-height:70vh; }
        .pos .ticket-top{ display:flex; gap:.6rem; padding:.85rem; border-bottom:1px solid var(--border); }
        .pos .ticket-top select{ flex:1; }
        .pos .iconbtn{ width:42px; display:flex; align-items:center; justify-content:center; border:1px solid var(--border);
                       border-radius:.6rem; background:var(--card); color:var(--accent); }
        .pos table{ width:100%; border-collapse:collapse; }
        .pos thead th{ text-align:left; font-size:.72rem; letter-spacing:.03em; color:var(--muted); text-transform:uppercase;
                       padding:.6rem .85rem; border-bottom:1px solid var(--border); }
        .pos tbody td{ padding:.55rem .85rem; border-bottom:1px solid var(--border); font-size:.9rem; vertical-align:middle; }
        .pos .qtybox{ display:inline-flex; align-items:center; gap:.4rem; }
        .pos .qtybtn{ width:26px; height:26px; border-radius:.45rem; border:1px solid var(--border); background:var(--bg);
                      color:var(--text); font-weight:700; line-height:1; }
        .pos .priceinp{ width:82px; text-align:right; padding:.3rem .4rem; }
        .pos .rm{ color:#ef4444; font-weight:700; cursor:pointer; }
        .pos .empty{ flex:1; display:flex; align-items:center; justify-content:center; color:var(--muted); text-align:center; padding:2rem; }
        .pos .ticket-foot{ margin-top:auto; padding:.85rem; border-top:1px solid var(--border); }
        .pos .totalbar{ display:flex; align-items:center; justify-content:space-between; padding:.9rem .85rem;
                        background:rgba(14,165,233,.08); border-radius:.7rem; margin-top:.6rem; }
        .pos .totalbar .big{ font-size:1.6rem; font-weight:800; }
        .pos .actions{ display:grid; grid-template-columns:1fr 1fr 1.4fr; gap:.6rem; margin-top:.75rem; }
        .pos .btn{ padding:.85rem; border-radius:.7rem; font-weight:700; font-size:.95rem; display:flex; align-items:center;
                   justify-content:center; gap:.4rem; border:0; cursor:pointer; }
        .pos .btn-cancel{ background:#fecaca; color:#991b1b; } .dark .pos .btn-cancel{ background:#7f1d1d; color:#fecaca; }
        .pos .btn-hold{ background:#fde68a; color:#92400e; } .dark .pos .btn-hold{ background:#78500a; color:#fde68a; }
        .pos .btn-pay{ background:#34d399; color:#064e3b; } .dark .pos .btn-pay{ background:#065f46; color:#d1fae5; }

        /* ---- Grilla de productos (derecha) ---- */
        .pos .grid-wrap{ padding:.85rem; display:flex; flex-direction:column; gap:.75rem; max-height:78vh; }
        .pos .cats{ display:flex; flex-wrap:wrap; gap:.4rem; }
        .pos .cat{ padding:.5rem .9rem; border-radius:.6rem; border:1px solid var(--border); background:var(--bg);
                   color:var(--text); font-size:.85rem; cursor:pointer; }
        .pos .cat.active{ background:var(--accent); color:#fff; border-color:var(--accent); }
        .pos .cards{ display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:.7rem; overflow-y:auto; padding-right:2px; }
        .pos .pcard{ position:relative; text-align:left; background:var(--card); border:1px solid var(--border); border-radius:.8rem;
                     padding:.8rem; cursor:pointer; transition:transform .08s, box-shadow .15s; }
        .pos .pcard:hover{ box-shadow:0 4px 12px rgba(14,165,233,.18); transform:translateY(-1px); }
        .pos .pcard .ic{ width:34px; height:34px; border-radius:.5rem; background:rgba(14,165,233,.12); color:var(--accent);
                         display:flex; align-items:center; justify-content:center; margin-bottom:.5rem; }
        .pos .pcard .ic svg{ width:20px; height:20px; }
        .pos .pcard .st{ position:absolute; top:.55rem; right:.55rem; min-width:22px; height:22px; padding:0 6px; border-radius:999px;
                         background:#fef3c7; color:#92400e; font-size:.72rem; font-weight:700; display:flex; align-items:center; justify-content:center; }
        .pos .pcard .st.zero{ background:#fee2e2; color:#991b1b; }
        .pos .pcard .nm{ font-weight:700; font-size:.9rem; line-height:1.15; }
        .pos .pcard .ty{ font-size:.75rem; color:var(--muted); }
        .pos .pcard .pr{ font-weight:800; color:var(--accent); margin-top:.35rem; }

        /* ---- Modal de pago ---- */
        .pos-modal{ position:fixed; inset:0; background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; z-index:50; }
        .pos-modal .box{ background:var(--card); color:var(--text); border-radius:1rem; padding:1.3rem; width:420px; max-width:92vw; box-shadow:0 20px 60px rgba(0,0,0,.4); }
        .pos-modal .pm{ display:grid; grid-template-columns:1fr 1fr; gap:.6rem; margin:1rem 0; }
        .pos-modal .pm button{ padding:.8rem; border-radius:.7rem; border:2px solid var(--border); background:var(--bg); color:var(--text); font-weight:600; cursor:pointer; }
        .pos-modal .pm button.sel{ border-color:var(--accent); background:rgba(14,165,233,.12); }
    </style>

    @php($cats = $this->categories())
    <div class="pos">
        {{-- ===== TICKET ===== --}}
        <div class="panel ticket">
            <div class="ticket-top">
                <select wire:model.live="branchId">
                    @foreach ($this->branches() as $b)
                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="customerId">
                    <option value="">Mostrador (sin cliente)</option>
                    @foreach ($this->customers() as $c)
                        <option value="{{ $c->id }}">{{ $c->name ?? 'Sin nombre' }} — {{ $c->phone }}</option>
                    @endforeach
                </select>
                <button class="iconbtn" wire:click="openNewCustomer" title="Nuevo cliente">
                    <x-heroicon-o-user-plus style="width:20px;height:20px;" />
                </button>
            </div>

            @if (empty($this->cart))
                <div class="empty">
                    <div>
                        <x-heroicon-o-shopping-cart style="width:40px;height:40px;margin:0 auto .5rem;opacity:.4;" />
                        Toca un producto de la derecha para agregarlo al ticket
                    </div>
                </div>
            @else
                <div style="overflow-y:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Artículo</th><th>Precio</th><th>Cantidad</th><th>Descuento</th>
                                <th style="text-align:right;">Subtotal</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->cart as $i => $line)
                                @php($sub = (float)$line['charged'] * $line['qty'])
                                @php($desc = max(0, ($line['list'] - (float)$line['charged'])) * $line['qty'])
                                <tr wire:key="line-{{ $i }}">
                                    <td>
                                        <div style="font-weight:600;">{{ $line['name'] }}</div>
                                        <div class="muted" style="font-size:.75rem;">{{ $line['unit'] }}</div>
                                    </td>
                                    <td>
                                        <input type="number" step="0.5" min="0" class="priceinp"
                                               wire:model.blur="cart.{{ $i }}.charged" />
                                    </td>
                                    <td>
                                        <span class="qtybox">
                                            <button class="qtybtn" wire:click="decQty({{ $i }})">−</button>
                                            <span style="min-width:22px;text-align:center;">{{ $line['qty'] }}</span>
                                            <button class="qtybtn" wire:click="incQty({{ $i }})">+</button>
                                        </span>
                                    </td>
                                    <td class="muted">{{ $desc > 0 ? 'S/ '.number_format($desc,2) : '—' }}</td>
                                    <td style="text-align:right;font-weight:700;">S/ {{ number_format($sub,2) }}</td>
                                    <td><span class="rm" wire:click="removeLine({{ $i }})">✕</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="ticket-foot">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span class="muted">Subtotal <strong style="color:var(--text);">S/ {{ number_format($this->subtotalLista(),2) }}</strong></span>
                    <span class="muted">Artículos <strong style="color:var(--text);">{{ $this->itemsCount() }} ({{ count($this->cart) }})</strong></span>
                    @if ($this->descuentoTotal() > 0)
                        <span class="muted">Descuento <strong style="color:#16a34a;">− S/ {{ number_format($this->descuentoTotal(),2) }}</strong></span>
                    @endif
                </div>
                <input type="text" wire:model="note" placeholder="Nota (opcional)" style="width:100%;margin-top:.6rem;" />

                <div class="totalbar">
                    <span style="font-weight:700;">TOTAL A PAGAR</span>
                    <span class="big" style="color:var(--accent);">S/ {{ number_format($this->totalCobrado(),2) }}</span>
                </div>

                <div class="actions">
                    <button class="btn btn-cancel" wire:click="cancelar">✕ Cancelar</button>
                    <button class="btn btn-hold" wire:click="enEspera">⏸ En espera</button>
                    <button class="btn btn-pay" wire:click="openPayment">✓ Pago</button>
                </div>
            </div>
        </div>

        {{-- ===== GRILLA DE PRODUCTOS ===== --}}
        <div class="panel">
            <div class="grid-wrap">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="🔍 Buscar producto por nombre" />
                <div class="cats">
                    @foreach ($cats as $key => $label)
                        <button class="cat {{ $this->categoryFilter === $key ? 'active' : '' }}"
                                wire:click="$set('categoryFilter','{{ $key }}')">{{ $label }}</button>
                    @endforeach
                </div>
                <div class="cards">
                    @forelse ($this->products() as $p)
                        <button class="pcard" wire:click="addProduct({{ $p['id'] }})" wire:key="prod-{{ $p['id'] }}">
                            <span class="st {{ $p['stock'] <= 0 ? 'zero' : '' }}">{{ $p['stock'] }}</span>
                            <span class="ic"><x-heroicon-s-cube /></span>
                            <div class="nm">{{ $p['name'] }}</div>
                            <div class="ty">{{ $p['type'] === 'recarga' ? 'Recarga' : 'Venta' }} · {{ $p['unit'] }}</div>
                            <div class="pr">S/ {{ number_format($p['price'],2) }}</div>
                        </button>
                    @empty
                        <p class="muted" style="grid-column:1/-1;text-align:center;padding:2rem;">No hay productos que coincidan.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- ===== MODAL NUEVO CLIENTE ===== --}}
    @if ($showNewCustomer)
        <div class="pos pos-modal" wire:key="cust-modal">
            <div class="box">
                <h3 style="font-size:1.1rem;font-weight:800;">Nuevo cliente</h3>
                <div style="display:flex;flex-direction:column;gap:.6rem;margin:1rem 0;">
                    <input type="text" wire:model="newName" placeholder="Nombre" />
                    <input type="text" wire:model="newPhone" placeholder="Teléfono (ej. +51987654321)" />
                </div>
                <div style="display:flex;gap:.6rem;">
                    <button class="btn btn-cancel" style="flex:1;" wire:click="$set('showNewCustomer',false)">Cancelar</button>
                    <button class="btn btn-pay" style="flex:1.4;" wire:click="saveCustomer">Guardar</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== MODAL DE PAGO ===== --}}
    @if ($showPayment)
        <div class="pos pos-modal" wire:key="pay-modal">
            <div class="box">
                <h3 style="font-size:1.1rem;font-weight:800;">Cobrar venta</h3>
                <p class="muted" style="font-size:.9rem;">Total a cobrar: <strong style="color:var(--accent);font-size:1.1rem;">S/ {{ number_format($this->totalCobrado(),2) }}</strong></p>

                <div style="font-size:.8rem;font-weight:600;margin-top:1rem;" class="muted">Método de pago</div>
                <div class="pm">
                    @forelse ($this->paymentMethods() as $m)
                        <button class="{{ $paymentMethodId === $m->id ? 'sel' : '' }}" wire:click="$set('paymentMethodId',{{ $m->id }})">
                            {{ $m->name }}
                        </button>
                    @empty
                        <p class="muted" style="grid-column:1/-1;">No hay métodos de pago. Agrégalos en Configuración.</p>
                    @endforelse
                </div>

                <div style="display:flex;gap:.6rem;margin-top:.5rem;">
                    <button class="btn btn-cancel" style="flex:1;" wire:click="closePayment">Cancelar</button>
                    <button class="btn btn-pay" style="flex:1.6;" wire:click="cobrar">Confirmar cobro</button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
