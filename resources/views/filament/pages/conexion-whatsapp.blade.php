<x-filament-panels::page>
    {{-- Datos para configurar el webhook en Meta --}}
    <x-filament::section>
        <x-slot name="heading">Configura el webhook en Meta</x-slot>
        <x-slot name="description">
            Pega estos datos en la configuración de tu app de WhatsApp en Meta.
        </x-slot>

        <div class="space-y-2 text-sm">
            <div>
                <span class="font-medium">URL de callback:</span>
                <code style="background:rgba(0,0,0,0.05);padding:2px 6px;border-radius:4px;">{{ $this->webhookUrl() }}</code>
            </div>
            <div>
                <span class="font-medium">Verify token:</span>
                <code style="background:rgba(0,0,0,0.05);padding:2px 6px;border-radius:4px;">{{ $this->verifyToken() }}</code>
            </div>
            <div>
                <span class="font-medium">Estado:</span>
                @php($estado = $this->connectionStatus())
                <span style="font-weight:600;color:{{ $estado === 'connected' ? '#16a34a' : ($estado === 'error' ? '#dc2626' : '#6b7280') }};">
                    {{ ['connected' => 'Conectado', 'error' => 'Error', 'disconnected' => 'Sin conectar'][$estado] ?? $estado }}
                </span>
            </div>
        </div>
    </x-filament::section>

    {{-- Formulario de credenciales --}}
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex gap-3">
            <x-filament::button type="submit">Guardar</x-filament::button>
            <x-filament::button type="button" color="gray" wire:click="testConnection">
                Probar conexión
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
