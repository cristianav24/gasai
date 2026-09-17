<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Importación masiva de clientes (migración desde WhatsApp). Protegida por un
 * token de un solo uso (MIGRATION_TOKEN). Crea clientes por teléfono o por
 * username de WhatsApp, sin duplicar. No toca conversaciones ni pedidos.
 */
class MigracionClientesController extends Controller
{
    public function importar(Request $request): JsonResponse
    {
        $token = (string) config('services.migration_token');
        if ($token === '' || ! hash_equals($token, (string) $request->input('token'))) {
            abort(403, 'Token inválido.');
        }

        $tenant = Tenant::where('slug', (string) $request->input('tenant'))->first();
        if (! $tenant) {
            abort(422, 'Negocio no encontrado.');
        }

        $creados = 0;
        $existentes = 0;

        foreach ((array) $request->input('phones', []) as $raw) {
            $phone = '+' . preg_replace('/\D/', '', (string) $raw);
            if (strlen($phone) < 9) {
                continue;
            }
            $existe = Customer::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)->where('phone', $phone)->exists();
            if ($existe) {
                $existentes++;
                continue;
            }
            Customer::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id, 'phone' => $phone, 'notes' => 'Migrado de WhatsApp',
            ]);
            $creados++;
        }

        foreach ((array) $request->input('usernames', []) as $raw) {
            $raw = trim((string) $raw);
            if ($raw === '') {
                continue;
            }
            $esUsername = str_starts_with($raw, '@');
            $username = $esUsername ? ltrim($raw, '@') : null;
            $name = $esUsername ? null : $raw; // contacto guardado con nombre, sin número visible

            $q = Customer::withoutGlobalScopes()->where('tenant_id', $tenant->id);
            $existe = $username
                ? (clone $q)->where('username', $username)->exists()
                : (clone $q)->where('name', $name)->exists();
            if ($existe) {
                $existentes++;
                continue;
            }
            Customer::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id, 'username' => $username, 'name' => $name, 'notes' => 'Migrado de WhatsApp',
            ]);
            $creados++;
        }

        return response()->json([
            'ok' => true,
            'creados' => $creados,
            'existentes' => $existentes,
            'total_clientes' => Customer::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
        ]);
    }

    /**
     * Clasifica clientes como 'cliente' o 'lead'. Recibe items { key, tipo }
     * donde key es un teléfono (dígitos) o un @username.
     */
    public function clasificar(Request $request): JsonResponse
    {
        $token = (string) config('services.migration_token');
        if ($token === '' || ! hash_equals($token, (string) $request->input('token'))) {
            abort(403, 'Token inválido.');
        }

        $tenant = Tenant::where('slug', (string) $request->input('tenant'))->first();
        if (! $tenant) {
            abort(422, 'Negocio no encontrado.');
        }

        $actualizados = 0;
        $noEncontrados = 0;

        foreach ((array) $request->input('items', []) as $item) {
            $key = trim((string) ($item['key'] ?? ''));
            $tipo = ($item['tipo'] ?? '') === 'cliente' ? 'cliente' : (($item['tipo'] ?? '') === 'lead' ? 'lead' : null);
            if ($key === '' || $tipo === null) {
                continue;
            }

            $q = Customer::withoutGlobalScopes()->where('tenant_id', $tenant->id);
            if (str_starts_with($key, '@')) {
                $q->where('username', ltrim($key, '@'));
            } elseif (preg_match('/\d/', $key)) {
                $q->where('phone', '+' . preg_replace('/\D/', '', $key));
            } else {
                $q->where('name', $key);
            }

            $customer = $q->first();
            if (! $customer) {
                $noEncontrados++;
                continue;
            }
            $customer->forceFill(['tipo' => $tipo])->save();
            $actualizados++;
        }

        return response()->json([
            'ok' => true,
            'actualizados' => $actualizados,
            'no_encontrados' => $noEncontrados,
            'clientes' => Customer::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('tipo', 'cliente')->count(),
            'leads' => Customer::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('tipo', 'lead')->count(),
        ]);
    }
}
