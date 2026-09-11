<?php

use App\Http\Controllers\LegalController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

// Página pública de inicio (marketing / describe el servicio).
Route::get('/', fn () => view('site.landing'))->name('landing');

// Páginas legales públicas (requeridas por Meta para la app de WhatsApp).
Route::get('/privacidad', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/terminos', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/eliminacion-datos', [LegalController::class, 'dataDeletion'])->name('legal.data-deletion');

// Ticket imprimible de una venta (térmico 80/58mm). La autorización (sesión y
// que la venta sea de un tenant del usuario) la resuelve el controlador.
Route::get('/ticket/venta/{sale}', [TicketController::class, 'sale'])->name('ticket.sale');

// Webhook único de WhatsApp (resuelve el tenant por phone_number_id).
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive']);
