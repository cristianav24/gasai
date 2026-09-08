<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registra el token de push del dispositivo del usuario (app móvil).
 */
class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'platform' => ['nullable', 'in:ios,android'],
        ]);

        $user = $request->user();
        $tenant = $user->tenants()->first();

        // updateOrCreate por token: si el dispositivo cambia de dueño, se reasigna.
        DeviceToken::withoutGlobalScopes()->updateOrCreate(
            ['token' => $data['token']],
            [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'platform' => $data['platform'] ?? null,
            ],
        );

        return response()->json(['ok' => true]);
    }
}
