<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Páginas legales públicas (privacidad, términos, eliminación de datos).
 * Requeridas por Meta para la app de WhatsApp; deben ser accesibles sin sesión.
 */
class LegalController extends Controller
{
    public function privacy(): View
    {
        return view('legal.privacidad');
    }

    public function terms(): View
    {
        return view('legal.terminos');
    }

    public function dataDeletion(): View
    {
        return view('legal.eliminacion-datos');
    }
}
