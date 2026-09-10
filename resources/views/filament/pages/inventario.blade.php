<x-filament-panels::page>
    @php
        $branches = $this->branches();
        $products = $this->products();
        $allProducts = $this->allProducts();
        $stock = $this->stockMap();
        $low = \App\Filament\Pages\Inventario::LOW_STOCK;

        // Resumen: total de existencias por producto sumando todas las sucursales.
        $totales = $allProducts->map(fn ($p) => collect($branches)->sum(fn ($b) => $stock["{$b->id}-{$p->id}"] ?? 0));
        $agotados = $totales->filter(fn ($t) => $t <= 0)->count();
        $bajos = $totales->filter(fn ($t) => $t > 0 && $t <= $low)->count();
        $ok = $totales->filter(fn ($t) => $t > $low)->count();
    @endphp

    <style>
        .inv {
            --card:#ffffff; --text:#0f172a; --muted:#64748b; --border:#e5e7eb; --soft:#f8fafc; --line:#eef2f7;
            --shadow:0 1px 2px rgba(15,23,42,.08);
        }
        .dark .inv { --card:#1e293b; --text:#f1f5f9; --muted:#94a3b8; --border:#334155; --soft:#0f1b30; --line:#233149; --shadow:0 1px 2px rgba(0,0,0,.4); }

        .inv .stats { display:grid; grid-template-columns:repeat(4,1fr); gap:.8rem; margin-bottom:1rem; }
        @media (max-width:800px){ .inv .stats{ grid-template-columns:repeat(2,1fr);} }
        .inv .stat { background:var(--card); border:1px solid var(--border); border-radius:.9rem; padding:.8rem 1rem; box-shadow:var(--shadow); }
        .inv .stat .n { font-size:1.5rem; font-weight:800; color:var(--text); line-height:1; }
        .inv .stat .l { font-size:.76rem; color:var(--muted); margin-top:.3rem; display:flex; align-items:center; gap:.35rem; }
        .inv .stat .dot { width:8px; height:8px; border-radius:50%; }

        .inv .panel { background:var(--card); border:1px solid var(--border); border-radius:1rem; box-shadow:var(--shadow); overflow:hidden; }
        .inv .p-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1rem 1.1rem; border-bottom:1px solid var(--border); flex-wrap:wrap; }
        .inv .p-title { font-weight:700; color:var(--text); }
        .inv .p-sub { font-size:.8rem; color:var(--muted); margin-top:1px; }
        .inv .search { display:flex; align-items:center; gap:.5rem; background:var(--soft); border:1px solid var(--border); border-radius:.7rem; padding:.4rem .7rem; }
        .inv .search svg { width:16px; height:16px; color:var(--muted); }
        .inv .search input { border:0; background:transparent; color:var(--text); font-size:.88rem; outline:none; min-width:180px; }

        .inv table { width:100%; border-collapse:collapse; }
        .inv thead th { text-align:left; font-size:.72rem; text-transform:uppercase; letter-spacing:.03em; color:var(--muted);
            padding:.6rem 1.1rem; border-bottom:1px solid var(--border); }
        .inv thead th.r { text-align:right; }
        .inv tbody td { padding:.7rem 1.1rem; border-bottom:1px solid var(--line); color:var(--text); }
        .inv tbody tr:last-child td { border-bottom:0; }
        .inv .pname { font-weight:600; }
        .inv .punit { font-size:.74rem; color:var(--muted); }
        .inv td.r { text-align:right; }
        .inv .badge { display:inline-flex; align-items:center; justify-content:center; min-width:44px; padding:.2rem .55rem;
            border-radius:999px; font-weight:800; font-size:.85rem; }
        .inv .badge.ok { background:rgba(34,197,94,.15); color:#15803d; }
        .dark .inv .badge.ok { color:#4ade80; }
        .inv .badge.low { background:rgba(245,158,11,.16); color:#b45309; }
        .dark .inv .badge.low { color:#fcd34d; }
        .inv .badge.out { background:rgba(239,68,68,.15); color:#dc2626; }
        .dark .inv .badge.out { color:#f87171; }
        .inv .empty { padding:2rem 1rem; text-align:center; color:var(--muted); font-size:.9rem; }
    </style>

    <div class="inv">
        {{-- Resumen --}}
        <div class="stats">
            <div class="stat"><div class="n">{{ $allProducts->count() }}</div><div class="l">Productos</div></div>
            <div class="stat"><div class="n" style="color:#22c55e;">{{ $ok }}</div><div class="l"><span class="dot" style="background:#22c55e;"></span>Con stock</div></div>
            <div class="stat"><div class="n" style="color:#f59e0b;">{{ $bajos }}</div><div class="l"><span class="dot" style="background:#f59e0b;"></span>Stock bajo (≤{{ $low }})</div></div>
            <div class="stat"><div class="n" style="color:#ef4444;">{{ $agotados }}</div><div class="l"><span class="dot" style="background:#ef4444;"></span>Agotados / negativo</div></div>
        </div>

        <div class="panel">
            <div class="p-head">
                <div>
                    <div class="p-title">Existencias por sucursal</div>
                    <div class="p-sub">El stock se descuenta automáticamente al entregar un pedido.</div>
                </div>
                <div class="search">
                    <x-heroicon-o-magnifying-glass />
                    <input type="text" placeholder="Buscar producto…" wire:model.live.debounce.300ms="search" />
                </div>
            </div>

            @if ($allProducts->isEmpty())
                <div class="empty">Aún no hay productos. Créalos en <strong>Productos</strong>.</div>
            @elseif ($products->isEmpty())
                <div class="empty">Ningún producto coincide con “{{ $search }}”.</div>
            @else
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                @foreach ($branches as $branch)
                                    <th class="r">{{ $branch->name }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($products as $product)
                                <tr>
                                    <td>
                                        <div class="pname">{{ $product->name }}</div>
                                        @if ($product->unit)<div class="punit">{{ $product->unit }}</div>@endif
                                    </td>
                                    @foreach ($branches as $branch)
                                        @php($qty = $stock["{$branch->id}-{$product->id}"] ?? 0)
                                        @php($cls = $qty <= 0 ? 'out' : ($qty <= $low ? 'low' : 'ok'))
                                        <td class="r"><span class="badge {{ $cls }}">{{ $qty }}</span></td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
