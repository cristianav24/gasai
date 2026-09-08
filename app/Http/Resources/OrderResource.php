<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'estado' => $this->status,
            'estado_label' => Order::LABELS[$this->status] ?? $this->status,
            'total' => (float) $this->total,
            'fecha_programada' => $this->scheduled_date?->toDateString(),
            'franja' => $this->scheduled_slot,
            'hora' => $this->scheduled_time,
            'notas' => $this->notes,
            'cliente' => [
                'nombre' => $this->customer?->name,
                'telefono' => $this->customer?->phone,
            ],
            'direccion' => [
                'texto' => $this->address?->address,
                'referencia' => $this->address?->reference,
                'lat' => $this->address?->lat !== null ? (float) $this->address->lat : null,
                'lng' => $this->address?->lng !== null ? (float) $this->address->lng : null,
            ],
            'items' => $this->items->map(fn ($i): array => [
                'producto' => $i->product_name,
                'cantidad' => $i->quantity,
            ])->all(),
        ];
    }
}
