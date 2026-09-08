<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Miembros del equipo</x-slot>
        <x-slot name="description">
            Dueños y operadores usan el panel; los repartidores usan la app móvil.
        </x-slot>

        <div class="space-y-2">
            @forelse ($this->members() as $m)
                <div class="flex items-center justify-between gap-3 py-2" style="border-bottom:1px solid #eee;">
                    <div>
                        <div class="font-medium">{{ $m['name'] }}</div>
                        <div class="text-sm text-gray-500">{{ $m['email'] }}</div>
                    </div>

                    <div class="flex items-center gap-2">
                        <select
                            wire:change="changeRole({{ $m['id'] }}, $event.target.value)"
                            class="text-sm rounded border-gray-300"
                            style="padding:2px 6px;"
                        >
                            @foreach (\App\Filament\Pages\Equipo::ROLES as $value => $label)
                                <option value="{{ $value }}" @selected($m['role'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>

                        <x-filament::button
                            size="xs"
                            color="danger"
                            wire:click="removeMember({{ $m['id'] }})"
                            wire:confirm="¿Quitar a {{ $m['name'] }} del equipo?"
                        >
                            Quitar
                        </x-filament::button>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500">Aún no hay miembros.</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-panels::page>
