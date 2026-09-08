<x-filament-panels::page>
    @php($grupos = $this->ordersByStatus())
    @php($couriers = $this->couriers())

    <div style="display:grid;grid-template-columns:repeat(4,minmax(220px,1fr));gap:1rem;overflow-x:auto;">
        @foreach ($this->columns() as $status)
            @php($pedidos = $grupos[$status] ?? collect())
            <div style="background:rgba(0,0,0,0.03);border-radius:0.75rem;padding:0.75rem;min-width:220px;">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-sm">{{ $this->label($status) }}</h3>
                    <span class="text-xs rounded-full px-2 py-0.5" style="background:rgba(0,0,0,0.08);">
                        {{ $pedidos->count() }}
                    </span>
                </div>

                <div class="space-y-3">
                    @forelse ($pedidos as $order)
                        <div class="rounded-lg p-3 text-sm" style="background:var(--fi-color-white,#fff);border:1px solid #e5e7eb;">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold">#{{ $order->id }}</span>
                                <span class="text-xs text-gray-500">S/ {{ number_format((float) $order->total, 2) }}</span>
                            </div>

                            <div class="mt-1 text-gray-700">
                                {{ $order->customer?->name ?? $order->customer?->phone ?? 'Sin cliente' }}
                            </div>

                            <div class="text-xs text-gray-500">
                                {{ $order->scheduled_date?->format('d/m') }}
                                · {{ \App\Models\Order::LABELS[$order->scheduled_slot] ?? ($order->scheduled_slot ?? '—') }}
                                @if ($order->scheduled_time) · {{ \Illuminate\Support\Str::of($order->scheduled_time)->substr(0,5) }} @endif
                            </div>

                            <div class="text-xs text-gray-500 mt-1">
                                {{ $order->items->sum('quantity') }} ítem(s)
                            </div>

                            {{-- Asignación de repartidor --}}
                            <div class="mt-2">
                                <select
                                    wire:change="assignCourier({{ $order->id }}, $event.target.value)"
                                    class="w-full text-xs rounded border-gray-300"
                                    style="padding:2px 4px;"
                                >
                                    <option value="">Sin repartidor</option>
                                    @foreach ($couriers as $courier)
                                        <option value="{{ $courier->id }}" @selected($order->courier_id === $courier->id)>
                                            {{ $courier->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Acciones --}}
                            <div class="mt-2 flex gap-2">
                                @if ($order->nextStatus())
                                    <x-filament::button size="xs" wire:click="advance({{ $order->id }})">
                                        → {{ $this->label($order->nextStatus()) }}
                                    </x-filament::button>
                                @endif
                                <x-filament::button size="xs" color="danger" wire:click="cancel({{ $order->id }})">
                                    Cancelar
                                </x-filament::button>
                            </div>

                            <div class="mt-2">
                                <a href="{{ $this->cobrarUrl($order->id) }}"
                                   class="text-xs font-semibold" style="color:#16a34a;">
                                    💵 Cobrar en POS
                                </a>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400">Sin pedidos.</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
