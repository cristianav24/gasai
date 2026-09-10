<?php

use App\Http\Controllers\TicketController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// Ticket imprimible de una venta (térmico 80/58mm). La autorización (sesión y
// que la venta sea de un tenant del usuario) la resuelve el controlador.
Route::get('/ticket/venta/{sale}', [TicketController::class, 'sale'])->name('ticket.sale');

// Webhook único de WhatsApp (resuelve el tenant por phone_number_id).
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive']);
