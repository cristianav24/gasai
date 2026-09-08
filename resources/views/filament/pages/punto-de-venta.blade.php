<x-filament-panels::page>
    @if ($orderId)
        <x-filament::section>
            <p class="text-sm">Cobrando el pedido <strong>#{{ $orderId }}</strong>. El cliente ya viene preseleccionado.</p>
        </x-filament::section>
    @endif

    <form wire:submit.prevent>
        {{ $this->form }}

        <div class="mt-6 flex items-center justify-between">
            <div class="text-lg font-bold">
                Total: S/ {{ number_format($this->total(), 2) }}
            </div>

            <div class="flex gap-3">
                <x-filament::button color="gray" wire:click="guardarEnEspera">
                    Guardar en espera
                </x-filament::button>
                <x-filament::button color="success" wire:click="cobrar">
                    Cobrar
                </x-filament::button>
            </div>
        </div>
    </form>
</x-filament-panels::page>
