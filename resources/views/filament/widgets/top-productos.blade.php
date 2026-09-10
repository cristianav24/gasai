<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Top productos del mes</x-slot>

        @php($items = $this->topProductos())

        @if ($items->isEmpty())
            <p style="font-size:.88rem;color:var(--gray-500,#6b7280);">Todavía no hay ventas este mes.</p>
        @else
            <div style="display:flex;flex-direction:column;gap:.6rem;">
                @foreach ($items as $i => $p)
                    <div style="display:flex;align-items:center;gap:.7rem;">
                        <span style="width:24px;height:24px;flex:0 0 24px;border-radius:50%;display:flex;align-items:center;justify-content:center;
                            font-weight:800;font-size:.78rem;background:rgba(245,158,11,.16);color:#b45309;">{{ $i + 1 }}</span>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:600;font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $p->name }}</div>
                            <div style="font-size:.76rem;opacity:.65;">{{ (int) $p->qty }} vendidos</div>
                        </div>
                        <div style="font-weight:700;font-size:.9rem;">S/ {{ number_format((float) $p->total, 2) }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
