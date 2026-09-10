<x-filament-panels::page>
    {{-- Datos para configurar el webhook en Meta (manual / referencia) --}}
    <x-filament::section>
        <x-slot name="heading">Estado de la conexión</x-slot>
        <x-slot name="description">
            Con Embedded Signup no necesitas configurar el webhook a mano; estos datos quedan de referencia.
        </x-slot>

        <div class="space-y-2 text-sm">
            <div>
                <span class="font-medium">URL de callback:</span>
                <code style="background:rgba(127,127,127,0.15);padding:2px 6px;border-radius:4px;">{{ $this->webhookUrl() }}</code>
            </div>
            <div>
                <span class="font-medium">Verify token:</span>
                <code style="background:rgba(127,127,127,0.15);padding:2px 6px;border-radius:4px;">{{ $this->verifyToken() }}</code>
            </div>
            <div>
                <span class="font-medium">Estado:</span>
                @php($estado = $this->connectionStatus())
                <span style="font-weight:600;color:{{ $estado === 'connected' ? '#16a34a' : ($estado === 'error' ? '#dc2626' : '#6b7280') }};">
                    {{ ['connected' => 'Conectado', 'error' => 'Error', 'pending' => 'Pendiente', 'disconnected' => 'Sin conectar'][$estado] ?? $estado }}
                </span>
            </div>
        </div>
    </x-filament::section>

    {{-- Embedded Signup: conexión en 1 clic --}}
    @if ($this->embeddedSignupEnabled())
        <x-filament::section>
            <x-slot name="heading">Conectar con WhatsApp</x-slot>
            <x-slot name="description">
                Inicia sesión con Facebook, elige tu cuenta de WhatsApp Business, agrega tu número y verifícalo.
                Al terminar, tu número queda conectado automáticamente.
            </x-slot>

            <div class="flex items-center gap-3">
                <x-filament::button id="wa-es-btn" icon="heroicon-o-chat-bubble-left-right">
                    Conectar con WhatsApp
                </x-filament::button>
                <span id="wa-es-status" class="text-sm" style="color:#6b7280;"></span>
            </div>
        </x-filament::section>

        <script>
            // SDK de Facebook.
            window.fbAsyncInit = function () {
                FB.init({
                    appId: '{{ $this->appId() }}',
                    autoLogAppEvents: true,
                    xfbml: true,
                    version: '{{ $this->graphVersion() }}',
                });
            };
            (function (d, s, id) {
                var js, fjs = d.getElementsByTagName(s)[0];
                if (d.getElementById(id)) return;
                js = d.createElement(s); js.id = id;
                js.src = 'https://connect.facebook.net/en_US/sdk.js';
                fjs.parentNode.insertBefore(js, fjs);
            }(document, 'script', 'facebook-jssdk'));

            // Captura del session info del Embedded Signup (WABA ID + phone number ID).
            window.__waEs = { phone_number_id: null, waba_id: null };
            window.addEventListener('message', function (event) {
                if (typeof event.origin !== 'string' || ! event.origin.endsWith('facebook.com')) return;
                try {
                    const data = JSON.parse(event.data);
                    if (data.type === 'WA_EMBEDDED_SIGNUP' && data.data) {
                        if (data.data.phone_number_id) window.__waEs.phone_number_id = data.data.phone_number_id;
                        if (data.data.waba_id) window.__waEs.waba_id = data.data.waba_id;
                    }
                } catch (e) { /* mensajes no-JSON de Facebook: ignorar */ }
            });

            // Botón: lanza el diálogo y, al volver con el código, lo entrega a Livewire.
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('#wa-es-btn');
                if (! btn) return;
                e.preventDefault();
                const status = document.getElementById('wa-es-status');

                if (typeof FB === 'undefined') {
                    status.textContent = 'Cargando Facebook… intenta de nuevo en unos segundos.';
                    return;
                }

                status.textContent = 'Abriendo WhatsApp…';
                FB.login(function (response) {
                    if (response.authResponse && response.authResponse.code) {
                        status.textContent = 'Conectando con GasAI…';
                        @this.completeEmbeddedSignup({
                            code: response.authResponse.code,
                            phone_number_id: window.__waEs.phone_number_id,
                            waba_id: window.__waEs.waba_id,
                        });
                    } else {
                        status.textContent = 'Cancelaste o faltaron permisos.';
                    }
                }, {
                    config_id: '{{ $this->configId() }}',
                    response_type: 'code',
                    override_default_response_type: true,
                    extras: { setup: {}, featureType: '', sessionInfoVersion: '3' },
                });
            });
        </script>
    @endif

    {{-- Conexión manual (avanzado): pegar credenciales a mano --}}
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">Conexión manual (avanzado)</x-slot>
        <x-slot name="description">
            Si prefieres, pega el Phone Number ID, WABA ID y Access Token permanente a mano.
        </x-slot>

        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-6 flex gap-3">
                <x-filament::button type="submit">Guardar</x-filament::button>
                <x-filament::button type="button" color="gray" wire:click="testConnection">
                    Probar conexión
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
