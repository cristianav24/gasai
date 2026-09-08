<x-filament-panels::page>
    @php($session = $this->current())

    @if ($session)
        <x-filament::section>
            <x-slot name="heading">Caja abierta</x-slot>
            <x-slot name="description">
                Abierta {{ $session->opened_at?->diffForHumans() }}
            </x-slot>

            @php($tiles = [
                ['Fondo inicial', (float) $session->opening_amount, false],
                ['Ventas en efectivo', $session->cashSalesTotal(), false],
                ['Movimientos netos', $session->movementsTotal(), false],
                ['Esperado en caja', $session->expectedCash(), true],
            ])
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;">
                @foreach ($tiles as [$label, $value, $strong])
                    <div style="background:rgba(0,0,0,0.03);border-radius:0.5rem;padding:0.75rem;">
                        <div class="text-xs text-gray-500">{{ $label }}</div>
                        <div style="font-weight:{{ $strong ? '800' : '600' }};font-size:{{ $strong ? '1.25rem' : '1rem' }};">
                            S/ {{ number_format($value, 2) }}
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($session->movements->isNotEmpty())
                <div class="mt-4">
                    <h4 class="text-sm font-semibold mb-2">Movimientos</h4>
                    <ul class="text-sm space-y-1">
                        @foreach ($session->movements as $m)
                            <li class="flex justify-between" style="border-bottom:1px solid #eee;padding:2px 0;">
                                <span>
                                    {{ $m->type === 'entrada' ? '➕' : '➖' }} {{ $m->reason }}
                                </span>
                                <span style="color:{{ $m->type === 'entrada' ? '#16a34a' : '#dc2626' }};">
                                    {{ $m->type === 'entrada' ? '+' : '−' }} S/ {{ number_format((float) $m->amount, 2) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-filament::section>
    @else
        <x-filament::section>
            <p class="text-sm text-gray-500">No hay una caja abierta. Usa “Abrir caja” para empezar el turno.</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
