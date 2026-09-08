<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Login del repartidor para la app móvil. Devuelve un token de Sanctum.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
        ]);

        $user = \App\Models\User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales incorrectas.'],
            ]);
        }

        // MVP: el repartidor pertenece a un tenant.
        $tenant = $user->tenants()->first();

        if (! $tenant) {
            throw ValidationException::withMessages([
                'email' => ['El usuario no pertenece a ningún negocio.'],
            ]);
        }

        $token = $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'usuario' => [
                'id' => $user->id,
                'nombre' => $user->name,
                'rol' => $user->roleIn($tenant),
            ],
            'negocio' => [
                'id' => $tenant->id,
                'nombre' => $tenant->name,
                'slug' => $tenant->slug,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }
}
