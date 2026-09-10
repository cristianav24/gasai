<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    /**
     * Ticket imprimible de una venta, en ancho térmico (80mm por defecto, 58mm
     * opcional con ?w=58). Requiere sesión y que la venta sea de un tenant del
     * usuario (las ventas están fuera del panel, así que autorizamos a mano).
     */
    public function sale(Request $request, int $sale)
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $venta = Sale::withoutGlobalScopes()
            ->with(['items', 'customer', 'paymentMethod', 'branch', 'tenant'])
            ->findOrFail($sale);

        abort_unless($user->tenants()->whereKey($venta->tenant_id)->exists(), 403);

        $width = (int) $request->query('w', 80);
        $width = in_array($width, [58, 80], true) ? $width : 80;

        return view('tickets.sale', ['sale' => $venta, 'width' => $width]);
    }
}
