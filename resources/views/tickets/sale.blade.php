<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket venta #{{ $sale->id }}</title>
    @php
        $disc = (float) $sale->discount_total;
        $fontBase = $width === 58 ? 10 : 12;
    @endphp
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #e5e7eb; }
        body { font-family: "Menlo", "Consolas", monospace; color: #000; }

        .toolbar { display: flex; gap: .5rem; justify-content: center; padding: 12px; flex-wrap: wrap; }
        .toolbar a, .toolbar button {
            font: inherit; font-size: 13px; padding: .5rem .9rem; border-radius: .5rem; cursor: pointer;
            border: 1px solid #cbd5e1; background: #fff; color: #0f172a; text-decoration: none;
        }
        .toolbar .primary { background: #f59e0b; border-color: #f59e0b; color: #1c1917; font-weight: 700; }
        .toolbar .active { border-color: #f59e0b; color: #b45309; font-weight: 700; }

        .ticket {
            width: {{ $width }}mm; background: #fff; margin: 0 auto 16px; padding: 4mm 3mm;
            font-size: {{ $fontBase }}px; line-height: 1.35; color: #000;
        }
        .ticket .c { text-align: center; }
        .ticket .b { font-weight: 700; }
        .ticket .biz { font-size: {{ $fontBase + 3 }}px; font-weight: 800; text-transform: uppercase; letter-spacing: .02em; }
        .ticket .muted { color: #333; }
        .ticket hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        .ticket .row { display: flex; justify-content: space-between; gap: 6px; }
        .ticket .items .it { margin: 3px 0; }
        .ticket .items .it .name { }
        .ticket .items .it .line { display: flex; justify-content: space-between; }
        .ticket .items .it .line .qp { color: #333; }
        .ticket .tot .row.g { font-size: {{ $fontBase + 4 }}px; font-weight: 800; margin-top: 3px; }
        .ticket .foot { text-align: center; margin-top: 8px; }

        @media print {
            html, body { background: #fff; }
            .toolbar { display: none; }
            .ticket { width: {{ $width }}mm; margin: 0; padding: 2mm; }
            @page { size: {{ $width }}mm auto; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="primary" onclick="window.print()">Imprimir</button>
        <a href="?w=80" class="{{ $width === 80 ? 'active' : '' }}">80&nbsp;mm</a>
        <a href="?w=58" class="{{ $width === 58 ? 'active' : '' }}">58&nbsp;mm</a>
    </div>

    <div class="ticket">
        <div class="c biz">{{ $sale->tenant?->name ?? 'Ticket' }}</div>
        @if ($sale->branch)
            <div class="c">{{ $sale->branch->name }}</div>
            @if ($sale->branch->address)
                <div class="c muted">{{ $sale->branch->address }}</div>
            @endif
        @endif

        <hr>

        <div class="row"><span class="b">Ticket</span><span>#{{ $sale->id }}</span></div>
        <div class="row"><span class="muted">Fecha</span><span>{{ $sale->created_at?->format('d/m/Y H:i') }}</span></div>
        <div class="row"><span class="muted">Cliente</span><span>{{ $sale->customer?->displayName() ?? 'Mostrador' }}</span></div>
        @if ($sale->order_id)
            <div class="row"><span class="muted">Pedido</span><span>#{{ $sale->order_id }}</span></div>
        @endif

        <hr>

        <div class="items">
            @foreach ($sale->items as $it)
                @php($imp = (float) $it->unit_price_charged * (int) $it->quantity)
                <div class="it">
                    <div class="name">{{ $it->product_name }}</div>
                    <div class="line">
                        <span class="qp">{{ (int) $it->quantity }} x {{ number_format((float) $it->unit_price_charged, 2) }}</span>
                        <span>{{ number_format($imp, 2) }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <hr>

        <div class="tot">
            <div class="row"><span class="muted">Subtotal</span><span>S/ {{ number_format((float) $sale->subtotal, 2) }}</span></div>
            @if ($disc > 0)
                <div class="row"><span class="muted">Descuento</span><span>- S/ {{ number_format($disc, 2) }}</span></div>
            @elseif ($disc < 0)
                <div class="row"><span class="muted">Recargo</span><span>+ S/ {{ number_format(abs($disc), 2) }}</span></div>
            @endif
            <div class="row g"><span>TOTAL</span><span>S/ {{ number_format((float) $sale->total, 2) }}</span></div>
        </div>

        <hr>

        <div class="row"><span class="muted">Pago</span><span>{{ $sale->paymentMethod?->name ?? '—' }}</span></div>

        <div class="foot">
            <div class="b">¡Gracias por su compra!</div>
            <div class="muted">{{ $sale->tenant?->name }}</div>
        </div>
    </div>

    <script>
        // Al abrir desde "Imprimir ticket" mostramos el diálogo de impresión.
        window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
    </script>
</body>
</html>
