# GasAI — App del Repartidor (Expo)

App móvil para que los repartidores vean sus pedidos asignados y los marquen como
entregados. Consume la API de GasAI (Laravel + Sanctum).

## Requisitos

- Node 18+ y la app **Expo Go** en tu celular (Android/iOS), o un emulador.
- El backend de GasAI corriendo y accesible desde el celular.

## Configuración

1. Instala dependencias:

   ```bash
   cd mobile
   npm install
   # Si Expo se queja de versiones:
   npx expo install --fix
   ```

2. Apunta la app a tu backend. Edita `app.json` → `expo.extra.apiBaseUrl`:

   - **Celular físico (Expo Go):** usa la IP de tu PC en la red local, no `localhost`.
     Ejemplo: `http://192.168.1.100:8000`. (En Windows, mira tu IP con `ipconfig`.)
   - **Alternativa:** una URL pública de `ngrok` (`ngrok http 8000`).

3. Arranca el backend escuchando en la red (no solo en localhost):

   ```bash
   # En la carpeta del proyecto Laravel
   php artisan serve --host=0.0.0.0 --port=8000
   ```

## Correr la app

```bash
npx expo start
```

Escanea el QR con Expo Go (o pulsa `a` para Android / `i` para iOS en un emulador).

## Probarla de punta a punta

La app necesita un repartidor (usuario con rol `courier`) y un pedido asignado.
Mientras no exista la gestión de repartidores en el panel, créalos con Tinker:

```bash
php artisan tinker
```

```php
use App\Models\{Tenant, User, Order, Branch};
use Illuminate\Support\Facades\Hash;

$tenant = Tenant::where('slug','h2o-wanka')->first();

$repartidor = User::firstOrCreate(
    ['email' => 'repartidor@h2o.pe'],
    ['name' => 'Repartidor Uno', 'password' => Hash::make('secreto123')]
);
$tenant->users()->syncWithoutDetaching([$repartidor->id => ['role' => 'courier']]);

// Asigna un pedido existente a este repartidor y ponlo "en_ruta":
$order = Order::withoutGlobalScopes()->where('tenant_id',$tenant->id)->latest()->first();
$order?->update(['courier_id' => $repartidor->id, 'status' => 'en_ruta']);
```

Entra en la app con `repartidor@h2o.pe` / `secreto123`.

## Estructura

- `app/` — pantallas (expo-router): `login`, `orders/` (lista), `orders/[id]` (detalle).
- `src/api.ts` — cliente HTTP de la API.
- `src/auth.ts` — token guardado con `expo-secure-store`.
- `src/config.ts` — URL base de la API.

## Push

Tras el login, la app pide permiso de notificaciones y registra su token de Expo
en el backend (`POST /api/device-tokens`). El backend avisa al repartidor cuando
se le asigna un pedido, y al dueño cuando entra un pedido nuevo. El push real solo
funciona en un **dispositivo físico** (no en emulador).
