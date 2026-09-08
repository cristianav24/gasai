<?php

namespace App\Http\Middleware;

use App\Filament\Pages\Onboarding;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si el tenant activo no terminó el onboarding, lo lleva al wizard en vez del
 * dashboard vacío. No atrapa la propia página de onboarding ni el logout, para
 * no crear un bucle ni una cárcel.
 */
class EnsureOnboardingCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        if ($tenant && ! $tenant->onboardingCompleted()) {
            $onboardingUrl = Onboarding::getUrl(tenant: $tenant);

            // Dejamos pasar la propia página del wizard y cualquier POST (guardados
            // del wizard, logout); solo redirigimos las navegaciones GET a otras páginas.
            if ($request->isMethod('GET') && ! $request->fullUrlIs($onboardingUrl . '*')) {
                return redirect($onboardingUrl);
            }
        }

        return $next($request);
    }
}
