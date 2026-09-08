<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-base font-semibold">Progreso de configuración</h3>
                <p class="text-sm text-gray-500">
                    @if ($this->getPendingCount() === 0)
                        ¡Todo listo! Tu negocio está completamente configurado.
                    @else
                        Te falta {{ $this->getPendingCount() }}
                        {{ \Illuminate\Support\Str::plural('paso', $this->getPendingCount()) }} para terminar.
                    @endif
                </p>
            </div>

            <x-filament::button color="gray" size="sm" wire:click="dismiss">
                Descartar
            </x-filament::button>
        </div>

        <ul class="mt-4 space-y-2">
            @foreach ($this->getItems() as $item)
                <li class="flex items-center gap-3 text-sm">
                    @if ($item['done'])
                        <span style="color:#16a34a;">✓</span>
                        <span>{{ $item['label'] }}</span>
                    @else
                        <span style="color:#d1d5db;">○</span>
                        @if ($item['url'])
                            <a href="{{ $item['url'] }}" class="text-primary-600 hover:underline" style="color:#d97706;">
                                {{ $item['label'] }}
                            </a>
                        @else
                            <span class="text-gray-500">{{ $item['label'] }} <em>(disponible próximamente)</em></span>
                        @endif
                    @endif
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
