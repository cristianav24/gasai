<?php

namespace App\Providers;

use App\Services\Agent\ToolRegistry;
use App\Services\Agent\Tools;
use App\Services\Llm\DeepSeekProvider;
use App\Services\Llm\LlmProvider;
use App\Services\Push\ExpoPushNotifier;
use App\Services\Push\PushNotifier;
use App\Services\WhatsApp\CloudApiGateway;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Proveedor de LLM. Cambiar a otro proveedor = cambiar solo este binding.
        $this->app->bind(LlmProvider::class, function ($app): LlmProvider {
            $cfg = config('services.deepseek');

            return new DeepSeekProvider(
                http: $app->make(HttpFactory::class),
                apiKey: (string) ($cfg['api_key'] ?? ''),
                baseUrl: (string) $cfg['base_url'],
                model: (string) $cfg['model'],
                timeout: (int) $cfg['timeout'],
            );
        });

        // Notificador push (Expo). Cambiar de proveedor = cambiar este binding.
        $this->app->bind(PushNotifier::class, function ($app): PushNotifier {
            return new ExpoPushNotifier($app->make(HttpFactory::class));
        });

        // Gateway de WhatsApp. Embedded Signup de Meta = cambiar solo esta clase.
        $this->app->bind(WhatsAppGateway::class, function ($app): WhatsAppGateway {
            $cfg = config('services.whatsapp');

            return new CloudApiGateway(
                http: $app->make(HttpFactory::class),
                graphUrl: (string) $cfg['graph_url'],
                graphVersion: (string) $cfg['graph_version'],
            );
        });

        // Embedded Signup: intercambio de código y suscripción del WABA.
        $this->app->bind(\App\Services\WhatsApp\EmbeddedSignupService::class, function ($app) {
            $cfg = config('services.whatsapp');

            return new \App\Services\WhatsApp\EmbeddedSignupService(
                http: $app->make(HttpFactory::class),
                graphUrl: (string) $cfg['graph_url'],
                graphVersion: (string) $cfg['graph_version'],
                appId: $cfg['app_id'] ?? null,
                appSecret: $cfg['app_secret'] ?? null,
            );
        });

        // Registro de herramientas del agente.
        $this->app->singleton(ToolRegistry::class, function ($app): ToolRegistry {
            return new ToolRegistry([
                $app->make(Tools\BuscarCliente::class),
                $app->make(Tools\GuardarCliente::class),
                $app->make(Tools\ListarDirecciones::class),
                $app->make(Tools\GuardarDireccion::class),
                $app->make(Tools\ValidarDireccion::class),
                $app->make(Tools\ListarProductos::class),
                $app->make(Tools\CalcularTotal::class),
                $app->make(Tools\CrearPedido::class),
                $app->make(Tools\EstadoPedido::class),
                $app->make(Tools\SaldoEnvases::class),
                $app->make(Tools\EscalarAHumano::class),
            ]);
        });
    }

    public function boot(): void
    {
        //
    }
}
