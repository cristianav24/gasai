<x-filament-panels::page>
    @php
        $grupos = $this->balances();
        $ultimos = $this->lastMovements();

        // Resumen: total de envases nuestros afuera y cuántos clientes los tienen.
        $totalFuera = $grupos->flatten()->sum(fn ($b) => max(0, (int) $b->balance));
        $clientes = $grupos->count();
    @endphp

    <style>
        .env {
            --card:#ffffff; --text:#0f172a; --muted:#64748b; --border:#e5e7eb; --soft:#f8fafc; --line:#eef2f7;
            --shadow:0 1px 2px rgba(15,23,42,.08);
        }
        .dark .env { --card:#1e293b; --text:#f1f5f9; --muted:#94a3b8; --border:#334155; --soft:#0f1b30; --line:#233149; --shadow:0 1px 2px rgba(0,0,0,.4); }

        .env .stats { display:grid; grid-template-columns:repeat(2,1fr); gap:.8rem; margin-bottom:1rem; max-width:520px; }
        .env .stat { background:var(--card); border:1px solid var(--border); border-radius:.9rem; padding:.8rem 1rem; box-shadow:var(--shadow); }
        .env .stat .n { font-size:1.5rem; font-weight:800; color:var(--text); line-height:1; }
        .env .stat .l { font-size:.76rem; color:var(--muted); margin-top:.3rem; }

        .env .panel { background:var(--card); border:1px solid var(--border); border-radius:1rem; box-shadow:var(--shadow); overflow:hidden; }
        .env .p-head { padding:1rem 1.1rem; border-bottom:1px solid var(--border); }
        .env .p-title { font-weight:700; color:var(--text); }
        .env .p-sub { font-size:.82rem; color:var(--muted); margin-top:2px; }

        .env .row { display:flex; align-items:center; gap:.8rem; padding:.85rem 1.1rem; border-bottom:1px solid var(--line); }
        .env .row:last-child { border-bottom:0; }
        .env .av { width:40px; height:40px; border-radius:50%; flex:0 0 40px; display:flex; align-items:center; justify-content:center;
            font-weight:800; font-size:.85rem; background:#fde68a; color:#92400e; }
        .dark .env .av { background:#78500a; color:#fde68a; }
        .env .who { flex:1; min-width:0; }
        .env .name { font-weight:600; color:var(--text); }
        .env .meta { font-size:.76rem; color:var(--muted); margin-top:1px; display:flex; flex-wrap:wrap; gap:.3rem .7rem; }
        .env .badges { display:flex; flex-wrap:wrap; gap:.4rem; justify-content:flex-end; }
        .env .badge { display:inline-flex; align-items:center; gap:.35rem; font-size:.8rem; font-weight:600; border-radius:999px; padding:.25rem .7rem; border:1px solid transparent; }
        .env .badge b { font-weight:800; }
        .env .badge.hold { background:rgba(245,158,11,.16); color:#b45309; border-color:rgba(245,158,11,.35); }
        .dark .env .badge.hold { color:#fcd34d; border-color:rgba(245,158,11,.3); }
        .env .badge.neg { background:rgba(239,68,68,.14); color:#dc2626; border-color:rgba(239,68,68,.3); }
        .dark .env .badge.neg { color:#f87171; }
        .env .empty { padding:2.2rem 1rem; text-align:center; color:var(--muted); font-size:.9rem; }
        .env .empty svg { width:34px; height:34px; margin:0 auto .5rem; opacity:.5; }

        @media (max-width:640px){
            .env .stats{ grid-template-columns:1fr; }
            .env .row{ flex-wrap:wrap; }
            .env .badges{ justify-content:flex-start; width:100%; }
        }
    </style>

    <div class="env">
        <div class="stats">
            <div class="stat"><div class="n" style="color:#f59e0b;">{{ $totalFuera }}</div><div class="l">Envases nuestros en la calle</div></div>
            <div class="stat"><div class="n">{{ $clientes }}</div><div class="l">Clientes con envases</div></div>
        </div>

        <div class="panel">
            <div class="p-head">
                <div class="p-title">Envases en poder de clientes</div>
                <div class="p-sub">Cada entrega de un envase nuevo suma solo; la recarga no cambia el saldo. Usa “Registrar devolución” cuando te devuelvan envases.</div>
            </div>

            @if ($grupos->isEmpty())
                <div class="empty">
                    <x-heroicon-o-archive-box />
                    Ningún cliente tiene envases nuestros pendientes.
                </div>
            @else
                @foreach ($grupos as $customerId => $balances)
                    @php($cliente = $balances->first()->customer)
                    @php($ultimo = $ultimos[$customerId] ?? null)
                    <div class="row">
                        <span class="av">{{ \Illuminate\Support\Str::of($cliente?->name ?? $cliente?->phone ?? 'S')->substr(0, 2)->upper() }}</span>
                        <div class="who">
                            <div class="name">{{ $cliente?->name ?? 'Sin nombre' }}</div>
                            <div class="meta">
                                @if ($cliente?->phone)<span>{{ $cliente->phone }}</span>@endif
                                @if ($ultimo)<span>Último movimiento: {{ \Illuminate\Support\Carbon::parse($ultimo)->isoFormat('D MMM YYYY') }}</span>@endif
                            </div>
                        </div>
                        <div class="badges">
                            @foreach ($balances as $b)
                                <span class="badge {{ $b->balance >= 0 ? 'hold' : 'neg' }}">
                                    {{ $b->containerType?->name ?? 'Envase' }} <b>{{ $b->balance }}</b>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</x-filament-panels::page>
