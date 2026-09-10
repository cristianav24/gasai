<x-filament-panels::page>
    @php($tenant = \Filament\Facades\Filament::getTenant())

    <style>
        .onb {
            --card:#ffffff; --text:#0f172a; --muted:#64748b; --border:#e5e7eb; --soft:#f8fafc;
            --shadow:0 10px 40px -14px rgba(15,23,42,.18), 0 2px 8px rgba(15,23,42,.05);
        }
        .dark .onb {
            --card:#161f33; --text:#f1f5f9; --muted:#94a3b8; --border:#2b3a54; --soft:#0f1b30;
            --shadow:0 18px 50px -18px rgba(0,0,0,.65), 0 2px 8px rgba(0,0,0,.4);
        }

        .onb { display:flex; flex-direction:column; gap:1.25rem; }

        /* Hero de bienvenida */
        .onb-hero {
            position:relative; overflow:hidden; border-radius:1.25rem; padding:1.6rem 1.8rem;
            background:linear-gradient(135deg, #0ea5e9 0%, #06b6d4 45%, #f59e0b 130%);
            color:#fff; box-shadow:0 12px 40px -16px rgba(6,182,212,.55);
        }
        .onb-hero::after {
            content:""; position:absolute; right:-40px; top:-40px; width:200px; height:200px; border-radius:50%;
            background:rgba(255,255,255,.12);
        }
        .onb-hero .badge {
            display:inline-flex; align-items:center; gap:.4rem; font-size:.74rem; font-weight:700; letter-spacing:.04em;
            text-transform:uppercase; background:rgba(255,255,255,.2); padding:.25rem .7rem; border-radius:999px;
        }
        .onb-hero h1 { font-size:1.6rem; font-weight:800; margin:.6rem 0 .3rem; line-height:1.15; }
        .onb-hero p { font-size:.95rem; opacity:.95; max-width:60ch; margin:0; }
        .onb-hero .meta { display:flex; flex-wrap:wrap; gap:.5rem .9rem; margin-top:.9rem; font-size:.82rem; }
        .onb-hero .meta span { display:inline-flex; align-items:center; gap:.35rem; background:rgba(255,255,255,.16);
            padding:.2rem .6rem; border-radius:999px; }

        /* Tarjeta que contiene el wizard */
        .onb-card {
            background:var(--card); border:1px solid var(--border); border-radius:1.25rem;
            box-shadow:var(--shadow); padding:1.6rem 1.6rem 1.4rem;
        }

        /* Retoques al wizard de Filament para un look más limpio */
        .onb-card .fi-wizard { border:0; box-shadow:none; background:transparent; }
        .onb-card .fi-wizard-header { padding-bottom:.4rem; }

        @media (max-width:640px){
            .onb-hero { padding:1.2rem 1.2rem; }
            .onb-hero h1 { font-size:1.3rem; }
            .onb-card { padding:1.1rem; }
        }
    </style>

    <div class="onb">
        <div class="onb-hero">
            <span class="badge">✦ GasAI</span>
            <h1>Configuremos {{ $tenant?->name ?? 'tu negocio' }}</h1>
            <p>En unos minutos tu asistente estará listo para atender y vender por WhatsApp. Puedes saltar pasos y volver cuando quieras.</p>
            <div class="meta">
                <span>⏱️ ~3 minutos</span>
                <span>💾 Se guarda a cada paso</span>
                <span>🤖 Pruébalo antes de conectar WhatsApp</span>
            </div>
        </div>

        <div class="onb-card">
            <form wire:submit="complete">
                {{ $this->form }}
            </form>
        </div>
    </div>
</x-filament-panels::page>
