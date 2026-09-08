<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Envases en poder de clientes</x-slot>
        <x-slot name="description">
            Saldo de bidones/balones nuestros que cada cliente tiene. Las entregas suman automáticamente; usa
            “Registrar devolución” cuando devuelvan envases.
        </x-slot>

        @php($grupos = $this->balances())

        @if ($grupos->isEmpty())
            <p class="text-sm text-gray-500">Ningún cliente tiene envases pendientes.</p>
        @else
            <div class="space-y-3">
                @foreach ($grupos as $balances)
                    @php($cliente = $balances->first()->customer)
                    <div style="border:1px solid #eee;border-radius:0.5rem;padding:0.75rem;">
                        <div class="font-medium">
                            {{ $cliente?->name ?? 'Sin nombre' }}
                            <span class="text-sm text-gray-500">— {{ $cliente?->phone }}</span>
                        </div>
                        <div class="mt-1 flex flex-wrap gap-2">
                            @foreach ($balances as $b)
                                <span class="text-sm rounded-full px-3 py-1"
                                      style="background:{{ $b->balance > 0 ? '#fef3c7' : '#dcfce7' }};">
                                    {{ $b->containerType?->name ?? 'Envase' }}: <strong>{{ $b->balance }}</strong>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
