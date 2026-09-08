<x-filament-panels::page>
    @php($selected = $this->selected())

    <div style="display:grid;grid-template-columns:300px 1fr;gap:1rem;min-height:480px;">

        {{-- Lista de conversaciones --}}
        <div style="border:1px solid #eee;border-radius:0.75rem;overflow:hidden;">
            @forelse ($this->conversations() as $c)
                <button
                    wire:click="select({{ $c->id }})"
                    style="display:block;width:100%;text-align:left;padding:0.75rem;border-bottom:1px solid #f0f0f0;
                           background:{{ $selectedId === $c->id ? 'rgba(245,158,11,0.12)' : 'transparent' }};"
                >
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-sm">
                            {{ $c->customer?->name ?? $c->phone ?? 'Anónimo' }}
                        </span>
                        <span class="text-xs rounded-full px-2 py-0.5"
                              style="background:{{ $c->status === 'humano' ? '#fee2e2' : '#dcfce7' }};">
                            {{ $c->status === 'humano' ? 'Humano' : 'Bot' }}
                        </span>
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ $c->channel }} · {{ $c->last_activity_at?->diffForHumans() }}
                    </div>
                </button>
            @empty
                <p class="text-sm text-gray-500 p-4">No hay conversaciones todavía.</p>
            @endforelse
        </div>

        {{-- Hilo seleccionado --}}
        <div style="border:1px solid #eee;border-radius:0.75rem;display:flex;flex-direction:column;">
            @if (! $selected)
                <div class="flex-1 flex items-center justify-center text-sm text-gray-500">
                    Selecciona una conversación.
                </div>
            @else
                {{-- Cabecera con controles --}}
                <div class="flex items-center justify-between p-3" style="border-bottom:1px solid #eee;">
                    <div>
                        <div class="font-semibold">{{ $selected->customer?->name ?? $selected->phone ?? 'Anónimo' }}</div>
                        <div class="text-xs text-gray-500">
                            Estado: {{ $selected->status === 'humano' ? 'Atendido por humano' : 'Atendido por el bot' }}
                        </div>
                    </div>
                    <div class="flex gap-2">
                        @if ($selected->status === 'humano')
                            <x-filament::button size="xs" color="gray" wire:click="returnToBot">
                                Devolver al agente
                            </x-filament::button>
                        @else
                            <x-filament::button size="xs" wire:click="takeControl">
                                Tomar control
                            </x-filament::button>
                        @endif
                    </div>
                </div>

                {{-- Mensajes --}}
                <div class="flex-1 p-3 space-y-2" style="overflow-y:auto;max-height:340px;">
                    @foreach ($this->thread() as $m)
                        <div class="flex {{ $m->role === 'user' ? 'justify-start' : 'justify-end' }}">
                            <div class="rounded-2xl px-3 py-2 text-sm" style="max-width:75%;white-space:pre-wrap;
                                 {{ $m->role === 'user' ? 'background:#fff;border:1px solid #e5e7eb;' : 'background:#f59e0b;color:#111;' }}">
                                {{ $m->content }}
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Entrada (solo cuando el humano tiene el control) --}}
                <div class="p-3" style="border-top:1px solid #eee;">
                    @if ($selected->status === 'humano')
                        <form wire:submit="sendMessage" class="flex gap-2">
                            <input type="text" wire:model="draft" placeholder="Escribe tu respuesta…"
                                   class="flex-1 rounded border-gray-300" style="padding:6px 10px;" />
                            <x-filament::button type="submit">Enviar</x-filament::button>
                        </form>
                    @else
                        <p class="text-xs text-gray-500">Toma el control para responder manualmente.</p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
