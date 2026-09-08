<x-filament-panels::page>
    @php($branches = $this->branches())
    @php($products = $this->products())
    @php($stock = $this->stockMap())

    <x-filament::section>
        <x-slot name="heading">Existencias por sucursal</x-slot>
        <x-slot name="description">El stock se descuenta automáticamente al entregar un pedido.</x-slot>

        @if ($products->isEmpty())
            <p class="text-sm text-gray-500">Aún no hay productos.</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
                    <thead>
                        <tr style="text-align:left;border-bottom:2px solid #e5e7eb;">
                            <th style="padding:8px;">Producto</th>
                            @foreach ($branches as $branch)
                                <th style="padding:8px;text-align:right;">{{ $branch->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $product)
                            <tr style="border-bottom:1px solid #f0f0f0;">
                                <td style="padding:8px;">{{ $product->name }}</td>
                                @foreach ($branches as $branch)
                                    @php($qty = $stock["{$branch->id}-{$product->id}"] ?? 0)
                                    <td style="padding:8px;text-align:right;font-weight:600;color:{{ $qty <= 0 ? '#dc2626' : '#111' }};">
                                        {{ $qty }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
