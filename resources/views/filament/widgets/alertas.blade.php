<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Alertas</x-slot>

        @php($a = $this->alertas())
        @php($hay = count($a['agotados']) || count($a['bajos']) || $a['sinRepartidor'] > 0)

        @if (! $hay)
            <div style="display:flex;align-items:center;gap:.5rem;font-size:.9rem;color:#16a34a;">
                <x-heroicon-o-check-circle style="width:20px;height:20px;" />
                Todo en orden, sin alertas.
            </div>
        @else
            <div style="display:flex;flex-direction:column;gap:.55rem;font-size:.88rem;">
                @if ($a['sinRepartidor'] > 0)
                    <div style="display:flex;align-items:center;gap:.55rem;">
                        <span style="width:9px;height:9px;border-radius:50%;background:#f59e0b;flex:0 0 9px;"></span>
                        <span><b>{{ $a['sinRepartidor'] }}</b> pedido(s) sin repartidor asignado.</span>
                    </div>
                @endif

                @foreach ($a['agotados'] as $nombre)
                    <div style="display:flex;align-items:center;gap:.55rem;">
                        <span style="width:9px;height:9px;border-radius:50%;background:#ef4444;flex:0 0 9px;"></span>
                        <span><b>{{ $nombre }}</b> — agotado.</span>
                    </div>
                @endforeach

                @foreach ($a['bajos'] as $b)
                    <div style="display:flex;align-items:center;gap:.55rem;">
                        <span style="width:9px;height:9px;border-radius:50%;background:#f59e0b;flex:0 0 9px;"></span>
                        <span><b>{{ $b['name'] }}</b> — stock bajo ({{ $b['qty'] }}).</span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
