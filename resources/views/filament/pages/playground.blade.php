<x-filament-panels::page>
    <style>
        .gasai-pg {
            --pg-surface: #ffffff; --pg-head: #f8fafc; --pg-text: #0f172a; --pg-muted: #64748b;
            --pg-border: #e2e8f0; --pg-wall: #eef2f6; --pg-wall-dot: rgba(100,116,139,.10);
            --pg-bot-bg: #ffffff; --pg-bot-text: #0f172a; --pg-bot-border: #e5e9f0;
            --pg-input: #ffffff; --pg-input-border: #e2e8f0;
            --pg-shadow: 0 1px 2px rgba(15,23,42,.10);
            --pg-card-shadow: 0 10px 40px -12px rgba(15,23,42,.22), 0 2px 8px rgba(15,23,42,.06);
        }
        .dark .gasai-pg {
            --pg-surface: #1e293b; --pg-head: #16233a; --pg-text: #f1f5f9; --pg-muted: #94a3b8;
            --pg-border: #2b3a54; --pg-wall: #0b1220; --pg-wall-dot: rgba(148,163,184,.06);
            --pg-bot-bg: #1e293b; --pg-bot-text: #f1f5f9; --pg-bot-border: #334155;
            --pg-input: #0f1b30; --pg-input-border: #334155;
            --pg-shadow: 0 1px 2px rgba(0,0,0,.4);
            --pg-card-shadow: 0 18px 50px -18px rgba(0,0,0,.7), 0 2px 8px rgba(0,0,0,.4);
        }

        .gasai-pg { max-width: 860px; margin: 0 auto; display: grid; gap: 1rem; }

        /* Tarjeta del chat */
        .gasai-pg .pgc-card {
            background: var(--pg-surface); border: 1px solid var(--pg-border);
            border-radius: 1.25rem; box-shadow: var(--pg-card-shadow); overflow: hidden;
            display: flex; flex-direction: column; height: calc(100vh - 210px); min-height: 460px;
        }

        /* Cabecera */
        .gasai-pg .pgc-head {
            display: flex; align-items: center; gap: .75rem; padding: .7rem .9rem;
            background: var(--pg-head); border-bottom: 1px solid var(--pg-border);
        }
        .gasai-pg .pgc-avatar {
            width: 42px; height: 42px; border-radius: 50%; flex: 0 0 42px;
            display: flex; align-items: center; justify-content: center; color: #fff;
            background: linear-gradient(135deg, #0ea5e9, #06b6d4);
            box-shadow: 0 3px 10px rgba(6,182,212,.4);
        }
        .gasai-pg .pgc-avatar svg { width: 23px; height: 23px; }
        .gasai-pg .pgc-id { flex: 1; min-width: 0; }
        .gasai-pg .pgc-name { font-weight: 700; font-size: .98rem; color: var(--pg-text); line-height: 1.15; display: flex; align-items: center; gap: .45rem; }
        .gasai-pg .pgc-tag {
            font-size: .64rem; font-weight: 700; letter-spacing: .01em; color: #b45309;
            background: rgba(245,158,11,.16); border: 1px solid rgba(245,158,11,.35);
            border-radius: 999px; padding: .05rem .4rem;
        }
        .dark .gasai-pg .pgc-tag { color: #fcd34d; background: rgba(245,158,11,.12); border-color: rgba(245,158,11,.3); }
        .gasai-pg .pgc-status { font-size: .74rem; color: #22c55e; display: flex; align-items: center; gap: .35rem; margin-top: 1px; }
        .gasai-pg .pgc-status .pgc-live {
            width: 7px; height: 7px; border-radius: 50%; background: #22c55e;
            box-shadow: 0 0 0 3px rgba(34,197,94,.18);
        }
        .gasai-pg .pgc-actions { display: flex; gap: .35rem; }
        .gasai-pg .pgc-iconbtn {
            width: 36px; height: 36px; border-radius: 50%; border: 1px solid var(--pg-border);
            background: var(--pg-surface); color: var(--pg-muted); cursor: pointer;
            display: flex; align-items: center; justify-content: center; transition: all .15s ease;
        }
        .gasai-pg .pgc-iconbtn:hover { color: var(--pg-text); border-color: var(--pg-muted); }
        .gasai-pg .pgc-iconbtn.on { color: #f59e0b; border-color: #f59e0b; }
        .gasai-pg .pgc-iconbtn svg { width: 18px; height: 18px; }

        /* Panel de ajustes (teléfono simulado) */
        .gasai-pg .pgc-settings {
            background: var(--pg-head); border-bottom: 1px solid var(--pg-border); padding: .85rem .9rem;
        }
        .gasai-pg .pgc-label { font-size: .78rem; font-weight: 600; color: var(--pg-text); display: block; margin-bottom: .35rem; }
        .gasai-pg .pgc-field {
            width: 100%; padding: .55rem .8rem; border-radius: .7rem; font-size: .9rem;
            background: var(--pg-input); border: 1px solid var(--pg-input-border); color: var(--pg-text);
        }
        .gasai-pg .pgc-field:focus { outline: none; border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,.15); }
        .gasai-pg .pgc-hint { font-size: .72rem; color: var(--pg-muted); margin-top: .4rem; }

        /* Hilo / wallpaper */
        .gasai-pg .pgc-body {
            flex: 1; min-height: 0; overflow-y: auto; padding: 1.1rem .9rem;
            display: flex; flex-direction: column; gap: .35rem; scroll-behavior: smooth;
            background-color: var(--pg-wall);
            background-image: radial-gradient(var(--pg-wall-dot) 1.5px, transparent 1.5px);
            background-size: 22px 22px;
        }
        .gasai-pg .pgc-msg { display: flex; max-width: 80%; }
        .gasai-pg .pgc-msg.user { align-self: flex-end; justify-content: flex-end; }
        .gasai-pg .pgc-msg.bot { align-self: flex-start; }
        .gasai-pg .pgc-bubble {
            display: flex; flex-direction: column; padding: .5rem .7rem .35rem;
            border-radius: 1.1rem; font-size: .9rem; line-height: 1.42;
            white-space: pre-wrap; word-break: break-word; box-shadow: var(--pg-shadow);
        }
        .gasai-pg .pgc-bubble.bot {
            background: var(--pg-bot-bg); color: var(--pg-bot-text);
            border: 1px solid var(--pg-bot-border); border-bottom-left-radius: .35rem;
        }
        .gasai-pg .pgc-bubble.user {
            background: #f59e0b; color: #1c1917; border-bottom-right-radius: .35rem;
        }
        .gasai-pg .pgc-meta {
            align-self: flex-end; display: flex; align-items: center; gap: 3px;
            font-size: .63rem; margin-top: 2px; line-height: 1;
        }
        .gasai-pg .pgc-bubble.bot .pgc-meta { color: var(--pg-muted); }
        .gasai-pg .pgc-bubble.user .pgc-meta { color: rgba(28,25,23,.55); }
        .gasai-pg .pgc-meta svg { width: 13px; height: 13px; }

        /* Estado vacío */
        .gasai-pg .pgc-empty {
            margin: auto; text-align: center; color: var(--pg-muted); font-size: .9rem;
            display: flex; flex-direction: column; align-items: center; gap: .1rem; max-width: 300px;
        }
        .gasai-pg .pgc-empty-ic {
            width: 60px; height: 60px; border-radius: 50%; margin-bottom: .6rem;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #0ea5e9, #06b6d4); color: #fff;
            box-shadow: 0 6px 18px rgba(6,182,212,.35);
        }
        .gasai-pg .pgc-empty-ic svg { width: 30px; height: 30px; }
        .gasai-pg .pgc-chip {
            margin-top: .8rem; font-size: .8rem; color: var(--pg-text); background: var(--pg-surface);
            border: 1px solid var(--pg-border); border-radius: 999px; padding: .35rem .8rem; box-shadow: var(--pg-shadow);
        }

        /* Escribiendo… */
        .gasai-pg .pgc-dots { display: inline-flex; gap: 4px; align-items: center; padding: .15rem 0; }
        .gasai-pg .pgc-dots span {
            width: 7px; height: 7px; border-radius: 50%; background: var(--pg-muted);
            animation: pgBounce 1.2s infinite ease-in-out both;
        }
        .gasai-pg .pgc-dots span:nth-child(2) { animation-delay: .15s; }
        .gasai-pg .pgc-dots span:nth-child(3) { animation-delay: .3s; }
        @keyframes pgBounce { 0%, 80%, 100% { transform: scale(.6); opacity: .5; } 40% { transform: scale(1); opacity: 1; } }
        @media (prefers-reduced-motion: reduce) { .gasai-pg .pgc-dots span { animation: none; } }

        /* Composer */
        .gasai-pg .pgc-foot {
            display: flex; align-items: center; gap: .55rem; padding: .65rem .75rem;
            background: var(--pg-head); border-top: 1px solid var(--pg-border);
        }
        .gasai-pg .pgc-input {
            flex: 1; padding: .7rem 1rem; border-radius: 999px; font-size: .92rem;
            background: var(--pg-input); border: 1px solid var(--pg-input-border); color: var(--pg-text);
        }
        .gasai-pg .pgc-input:focus { outline: none; border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,.15); }
        .gasai-pg .pgc-input::placeholder { color: var(--pg-muted); }
        .gasai-pg .pgc-send {
            width: 46px; height: 46px; flex: 0 0 46px; border-radius: 50%; border: 0; cursor: pointer;
            display: flex; align-items: center; justify-content: center; color: #fff;
            background: linear-gradient(135deg, #f59e0b, #f97316);
            box-shadow: 0 4px 12px rgba(245,158,11,.4); transition: transform .12s ease, opacity .15s ease;
        }
        .gasai-pg .pgc-send:hover:not(:disabled) { transform: scale(1.06); }
        .gasai-pg .pgc-send:disabled { opacity: .55; cursor: default; }
        .gasai-pg .pgc-send svg { width: 20px; height: 20px; }
        .gasai-pg .pgc-spin { width: 20px; height: 20px; animation: pgSpin .8s linear infinite; }
        @keyframes pgSpin { to { transform: rotate(360deg); } }

        /* Debug */
        .gasai-pg .pg-debug {
            background: #0b1020; color: #e2e8f0; border-radius: .8rem; padding: .85rem;
            font-size: .72rem; overflow: auto; border: 1px solid #1e293b;
        }
        .gasai-pg .pg-debug .k { color: #fbbf24; font-weight: 700; }
        .gasai-pg .pg-debug .m { color: #94a3b8; }

        @media (max-width: 640px) {
            .gasai-pg .pgc-card { height: calc(100vh - 170px); min-height: 380px; }
            .gasai-pg .pgc-msg { max-width: 88%; }
        }
    </style>

    <div class="gasai-pg" x-data="{ showSettings: false }" x-on:run-agent.window="$wire.runAgent()">

        {{-- Tarjeta de chat --}}
        <div class="pgc-card">

            {{-- Cabecera con identidad del agente --}}
            <div class="pgc-head">
                <div class="pgc-avatar">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M12 2.2c.28 0 .54.12.72.33 1.3 1.53 6.03 7.36 6.03 11.16A6.75 6.75 0 0 1 12 20.5a6.75 6.75 0 0 1-6.75-6.81c0-3.8 4.73-9.63 6.03-11.16.18-.21.44-.33.72-.33z" />
                    </svg>
                </div>
                <div class="pgc-id">
                    <div class="pgc-name">Manu <span class="pgc-tag">Prueba</span></div>
                    <div class="pgc-status"><span class="pgc-live"></span> en línea</div>
                </div>
                <div class="pgc-actions">
                    <button type="button" class="pgc-iconbtn" title="Ajustes"
                        :class="{ 'on': showSettings }" x-on:click="showSettings = !showSettings">
                        <x-heroicon-o-adjustments-horizontal />
                    </button>
                    <button type="button" class="pgc-iconbtn" title="Nueva conversación" wire:click="startNewConversation">
                        <x-heroicon-o-arrow-path />
                    </button>
                </div>
            </div>

            {{-- Ajustes: teléfono simulado --}}
            <div class="pgc-settings" x-show="showSettings" x-cloak>
                <label class="pgc-label">Teléfono del cliente (simulado)</label>
                <input type="text" class="pgc-field" wire:model="simPhone" placeholder="+51999999999" />
                <p class="pgc-hint">Simula el número del cliente. Si ya existe, el agente lo reconoce. Cámbialo y pulsa
                    <span style="color: var(--pg-text); font-weight:600;">Nueva conversación</span> para empezar de cero.</p>
            </div>

            {{-- Hilo --}}
            <div class="pgc-body" x-data
                 x-init="() => { const el = $el; const b = () => el.scrollTop = el.scrollHeight; b();
                                 new MutationObserver(b).observe(el, { childList: true, subtree: true }); }">
                @forelse ($thread as $msg)
                    @php($isUser = $msg['role'] === 'user')
                    <div class="pgc-msg {{ $isUser ? 'user' : 'bot' }}">
                        <div class="pgc-bubble {{ $isUser ? 'user' : 'bot' }}">
                            <span>{{ $msg['content'] }}</span>
                            <span class="pgc-meta">
                                {{ $msg['time'] }}
                                @if ($isUser)<x-heroicon-s-check />@endif
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="pgc-empty">
                        <div class="pgc-empty-ic"><x-heroicon-s-chat-bubble-left-right /></div>
                        Escribe un mensaje para conversar con tu agente.
                        <span class="pgc-chip">Prueba: “Hola, ¿qué venden?”</span>
                    </div>
                @endforelse

                {{-- Escribiendo… --}}
                @if ($pending)
                    <div class="pgc-msg bot">
                        <div class="pgc-bubble bot">
                            <span class="pgc-dots"><span></span><span></span><span></span></span>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Composer --}}
            <form wire:submit="send" class="pgc-foot">
                <input type="text" class="pgc-input" wire:model="input"
                    placeholder="Escribe como si fueras el cliente…" autocomplete="off"
                    x-bind:disabled="$wire.pending" />
                <button type="submit" class="pgc-send" title="Enviar" x-bind:disabled="$wire.pending">
                    <svg x-show="! $wire.pending" xmlns="http://www.w3.org/2000/svg"
                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 2 11 13" /><path d="M22 2 15 22l-4-9-9-4 20-7z" />
                    </svg>
                    <svg x-show="$wire.pending" x-cloak class="pgc-spin" xmlns="http://www.w3.org/2000/svg"
                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M21 12a9 9 0 1 1-6.219-8.56" />
                    </svg>
                </button>
            </form>
        </div>

        {{-- Debug --}}
        <div>
            <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" wire:model.live="debug" />
                Mostrar herramientas usadas (debug)
            </label>

            @if ($debug && ! empty($lastTrace))
                <div class="pg-debug mt-2">
                    @foreach ($lastTrace as $step)
                        <div class="mb-2">
                            <span class="k">{{ $step['tool'] }}</span>
                            <div><span class="m">args:</span> {{ json_encode($step['arguments'], JSON_UNESCAPED_UNICODE) }}</div>
                            <div><span class="m">result:</span> {{ json_encode($step['result'], JSON_UNESCAPED_UNICODE) }}</div>
                        </div>
                    @endforeach
                </div>
            @elseif ($debug)
                <p class="mt-2 text-xs" style="color: var(--pg-muted);">El último turno no usó herramientas.</p>
            @endif
        </div>

    </div>
</x-filament-panels::page>
