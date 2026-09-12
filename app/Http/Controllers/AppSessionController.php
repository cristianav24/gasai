<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Puente para la app híbrida: convierte un token de API (Sanctum) válido en una
 * sesión web, para que el WebView entre al panel ya autenticado (sin re-login).
 */
class AppSessionController extends Controller
{
    public function login(Request $request)
    {
        $plain = (string) $request->query('token', '');

        $token = $plain !== '' ? PersonalAccessToken::findToken($plain) : null;
        $user = $token?->tokenable;

        if (! $user) {
            return redirect('/admin/login');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect('/admin');
    }
}
