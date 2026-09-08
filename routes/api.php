<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\OrderApiController;
use Illuminate\Support\Facades\Route;

// API para la app móvil del repartidor.
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/orders', [OrderApiController::class, 'index']);
    Route::get('/orders/{order}', [OrderApiController::class, 'show']);
    Route::post('/orders/{order}/delivered', [OrderApiController::class, 'markDelivered']);

    Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
});
