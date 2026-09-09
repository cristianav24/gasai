<x-filament-panels::page>
    @php($grupos = $this->ordersByStatus())
    @php($couriers = $this->couriers())

    <style>
        .gasai-board { --col: #f1f5f9; --card: #ffffff; --text: #0f172a; --muted: #64748b; --border: #e5e7eb; --chip: #e2e8f0; }
        .dark .gasai-board { --col: #111827; --card: #1f2937; --text: #f3f4f6; --muted: #9ca3af; --border: #374151; --chip: #374151; }
        .gasai-board .col { background: var(--col); border-radius: 0.75rem; padding: 0.75rem; min-width: 220px; }
        .gasai-board .chip { background: var(--chip); color: var(--text); }
        .gasai-board .card { background: var(--card); color: var(--text); border: 1px solid var(--border); }
        .gasai-board .muted { color: var(--muted); }
        .gasai-board select { background: var(--card); color: var(--text); border: 1px solid var(--border); }
    </style>

    <div class="gasai-board" style="display:grid;grid-template-columns:repeat(4,minmax(220px,1fr));gap:1rem;overflow-x:auto;">
        @foreach ($this->columns() as $status)
            @php($pedidos = $grupos[$status] ?? collect())
            <div class="col">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-sm">{{ $this->label($status) }}</h3>
                    <span class="text-xs rounded-full px-2 py-0.5 chip">{{ $pedidos->count() }}</span>
                </div>

                <div class="space-y-3">
                    @forelse ($pedidos as $order)
                        <div class="card rounded-lg p-3 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold">#{{ $order->id }}</span>
                                <span class="text-xs muted">S/ {{ number_format((float) $order->total, 2) }}</span>
                            </div>

                            <div class="mt-1">
                                {{ $order->customer?->name ?? $order->customer?->phone ?? 'Sin cliente' }}
                            </div>

                            <div class="text-xs muted">
                                {{ $order->scheduled_date?->format('d/m') }}
                                · {{ \App\Models\Order::LABELS[$order->scheduled_slot] ?? ($order->scheduled_slot ?? '—') }}
                                @if ($order->scheduled_time) · {{ \Illuminate\Support\Str::of($order->scheduled_time)->substr(0,5) }} @endif
                            </div>

                            <div class="text-xs muted mt-1">
                                {{ $order->items->sum('quantity') }} ítem(s)
                            </div>

                            {{-- Asignación de repartidor --}}
                            <div class="mt-2">
                                <select
                                    wire:change="assignCourier({{ $order->id }}, $event.target.value)"
                                    class="w-full text-xs rounded"
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
                        <p class="text-xs muted">Sin pedidos.</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
