@php
    $statusColors = ['cobrada' => '#22c55e', 'en_espera' => '#f59e0b', 'cancelada' => '#ef4444'];
    $sc = $statusColors[$sale->status] ?? '#64748b';
@endphp

<div class="sv" style="display:flex;flex-direction:column;gap:1rem;font-size:.9rem;">
    <style>
        .sv { --sv-muted:#64748b; --sv-border:#e2e8f0; --sv-soft:#f1f5f9; --sv-text:#0f172a; }
        .dark .sv { --sv-muted:#94a3b8; --sv-border:#334155; --sv-soft:#1e293b; --sv-text:#f1f5f9; }
        .sv .sv-top { display:flex;align-items:center;justify-content:space-between;gap:1rem; }
        .sv .sv-total { font-size:1.5rem;font-weight:800;color:var(--sv-text); }
        .sv .sv-badge { font-size:.75rem;font-weight:700;color:#fff;border-radius:999px;padding:.2rem .7rem; }
        .sv .sv-meta { display:grid;grid-template-columns:repeat(2,1fr);gap:.7rem 1.2rem; }
        .sv .sv-meta .k { font-size:.7rem;text-transform:uppercase;letter-spacing:.03em;color:var(--sv-muted); }
        .sv .sv-meta .v { color:var(--sv-text);margin-top:1px; }
        .sv table { width:100%;border-collapse:collapse; }
        .sv th { text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.03em;color:var(--sv-muted);
            padding:.4rem .5rem;border-bottom:1px solid var(--sv-border); }
        .sv th.r, .sv td.r { text-align:right; }
        .sv td { padding:.5rem .5rem;border-bottom:1px solid var(--sv-border);color:var(--sv-text); }
        .sv .sv-tot { background:var(--sv-soft);border-radius:.7rem;padding:.7rem .9rem;margin-left:auto;max-width:280px;width:100%; }
        .sv .sv-tot .row { display:flex;justify-content:space-between;padding:.15rem 0;color:var(--sv-muted); }
        .sv .sv-tot .row.g { color:var(--sv-text);font-weight:800;font-size:1.05rem;border-top:1px solid var(--sv-border);
            margin-top:.3rem;padding-top:.5rem; }
        .sv .sv-notes { background:var(--sv-soft);border-radius:.7rem;padding:.7rem .9rem;color:var(--sv-text);white-space:pre-wrap; }
    </style>

    <div class="sv-top">
        <div class="sv-total">S/ {{ number_format((float) $sale->total, 2) }}</div>
        <span class="sv-badge" style="background:{{ $sc }};">{{ \App\Models\Sale::LABELS[$sale->status] ?? $sale->status }}</span>
    </div>

    <div class="sv-meta">
        <div><div class="k">Fecha</div><div class="v">{{ $sale->created_at?->format('d/m/Y H:i') }}</div></div>
        <div><div class="k">Cliente</div><div class="v">{{ $sale->customer?->displayName() ?? 'Mostrador' }}</div></div>
        <div><div class="k">Método de pago</div><div class="v">{{ $sale->paymentMethod?->name ?? '—' }}</div></div>
        <div><div class="k">Cajero</div><div class="v">{{ $sale->cashier?->name ?? '—' }}</div></div>
        <div><div class="k">Origen</div><div class="v">{{ $sale->order_id ? 'Pedido #' . $sale->order_id : 'Venta directa (mostrador)' }}</div></div>
        <div><div class="k">Sucursal</div><div class="v">{{ $sale->branch?->name ?? '—' }}</div></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th class="r">Cant.</th>
                <th class="r">Precio</th>
                <th class="r">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $it)
                <tr>
                    <td>{{ $it->product_name }}</td>
                    <td class="r">{{ (int) $it->quantity }}</td>
                    <td class="r">S/ {{ number_format((float) $it->unit_price_charged, 2) }}</td>
                    <td class="r">S/ {{ number_format((float) $it->unit_price_charged * (int) $it->quantity, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php($disc = (float) $sale->discount_total)
    <div class="sv-tot">
        <div class="row"><span>Subtotal (lista)</span><span>S/ {{ number_format((float) $sale->subtotal, 2) }}</span></div>
        @if ($disc > 0)
            <div class="row"><span>Descuento</span><span>− S/ {{ number_format($disc, 2) }}</span></div>
        @elseif ($disc < 0)
            <div class="row"><span>Recargo</span><span>+ S/ {{ number_format(abs($disc), 2) }}</span></div>
        @endif
        <div class="row g"><span>Total cobrado</span><span>S/ {{ number_format((float) $sale->total, 2) }}</span></div>
    </div>

    @if ($sale->notes)
        <div>
            <div class="k" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.03em;color:var(--sv-muted);margin-bottom:.3rem;">Notas</div>
            <div class="sv-notes">{{ $sale->notes }}</div>
        </div>
    @endif
</div>
