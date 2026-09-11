<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GasAI · Vende y atiende por WhatsApp con un asistente inteligente</title>
    <meta name="description" content="GasAI es la plataforma para negocios de reparto (agua, gas y más): un asistente que atiende WhatsApp, toma pedidos y gestiona despacho, ventas, caja e inventario.">
    <meta name="robots" content="index,follow">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: #f8fafc; color: #0f172a;
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; line-height: 1.6;
        }
        a { color: inherit; text-decoration: none; }
        .wrap { max-width: 1080px; margin: 0 auto; padding: 0 20px; }

        /* Nav */
        .nav { position: sticky; top: 0; z-index: 10; background: rgba(248,250,252,.85); backdrop-filter: blur(8px);
            border-bottom: 1px solid #e2e8f0; }
        .nav .row { display: flex; align-items: center; justify-content: space-between; height: 64px; }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 1.2rem; letter-spacing: -.01em; }
        .brand .dot { width: 30px; height: 30px; border-radius: 9px; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #0ea5e9, #06b6d4); color: #fff; font-size: 1rem; }
        .btn { display: inline-flex; align-items: center; gap: .4rem; font-weight: 700; padding: .6rem 1.15rem; border-radius: .7rem;
            background: linear-gradient(135deg, #f59e0b, #f97316); color: #1c1917; box-shadow: 0 4px 14px rgba(245,158,11,.35); }
        .btn.ghost { background: #fff; color: #0f172a; border: 1px solid #e2e8f0; box-shadow: none; }

        /* Hero */
        .hero { position: relative; overflow: hidden; padding: 72px 0 64px;
            background: radial-gradient(1000px 400px at 80% -10%, rgba(6,182,212,.18), transparent 60%),
                        radial-gradient(800px 400px at 0% 10%, rgba(245,158,11,.12), transparent 55%); }
        .hero h1 { font-size: 2.8rem; line-height: 1.08; font-weight: 800; letter-spacing: -.03em; margin: 0 0 .8rem; max-width: 15ch; }
        .hero h1 .grad { background: linear-gradient(135deg, #0ea5e9, #06b6d4); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .hero p { font-size: 1.18rem; color: #475569; max-width: 52ch; margin: 0 0 1.6rem; }
        .hero .cta { display: flex; gap: .8rem; flex-wrap: wrap; }
        .hero .chips { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: 1.6rem; }
        .hero .chips span { font-size: .84rem; color: #0f172a; background: #fff; border: 1px solid #e2e8f0; border-radius: 999px;
            padding: .3rem .8rem; }

        /* Secciones */
        section { padding: 56px 0; }
        .eyebrow { color: #0891b2; font-weight: 700; font-size: .8rem; letter-spacing: .08em; text-transform: uppercase; }
        h2 { font-size: 1.9rem; font-weight: 800; letter-spacing: -.02em; margin: .3rem 0 .5rem; }
        .lead { color: #475569; font-size: 1.05rem; max-width: 60ch; }

        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-top: 32px; }
        @media (max-width: 860px) { .grid { grid-template-columns: 1fr; } .hero h1 { font-size: 2.1rem; } }
        .feat { background: #fff; border: 1px solid #e8edf3; border-radius: 16px; padding: 22px;
            box-shadow: 0 1px 2px rgba(15,23,42,.04); }
        .feat .ic { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; margin-bottom: 12px; background: #ecfeff; }
        .feat h3 { margin: 0 0 6px; font-size: 1.08rem; font-weight: 700; }
        .feat p { margin: 0; color: #475569; font-size: .94rem; }

        /* Cómo funciona */
        .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-top: 32px; }
        @media (max-width: 860px) { .steps { grid-template-columns: 1fr; } }
        .step { padding: 22px; border-radius: 16px; background: #0f172a; color: #e2e8f0; }
        .step .n { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-weight: 800; background: #f59e0b; color: #1c1917; margin-bottom: 12px; }
        .step h3 { margin: 0 0 6px; color: #fff; font-size: 1.05rem; }
        .step p { margin: 0; color: #94a3b8; font-size: .94rem; }

        /* CTA final */
        .cta-band { background: linear-gradient(135deg, #0ea5e9, #06b6d4 55%, #f59e0b 140%); color: #fff; border-radius: 24px;
            padding: 44px; text-align: center; }
        .cta-band h2 { color: #fff; }
        .cta-band p { color: rgba(255,255,255,.92); max-width: 50ch; margin: 0 auto 1.4rem; }

        footer { border-top: 1px solid #e2e8f0; padding: 32px 0; color: #64748b; font-size: .9rem; }
        footer .row { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
        footer a { color: #64748b; }
        footer a:hover { color: #0f172a; }
    </style>
</head>
<body>
    <nav class="nav">
        <div class="wrap row">
            <span class="brand"><span class="dot">💧</span> GasAI</span>
            <a class="btn" href="/admin">Ingresar</a>
        </div>
    </nav>

    <header class="hero">
        <div class="wrap">
            <h1>Vende y atiende por <span class="grad">WhatsApp</span> con un asistente que trabaja por ti.</h1>
            <p>GasAI es la plataforma para negocios de reparto —agua, gas y más—: un asistente inteligente responde a tus clientes, toma pedidos y coordina la entrega, mientras tú controlas todo desde un panel.</p>
            <div class="cta">
                <a class="btn" href="/admin">Ingresar al panel →</a>
                <a class="btn ghost" href="#funciones">Ver qué incluye</a>
            </div>
            <div class="chips">
                <span>🤖 Asistente 24/7</span>
                <span>🛒 Toma pedidos sola</span>
                <span>🚚 Despacho y entregas</span>
                <span>🧾 Ventas, caja e inventario</span>
            </div>
        </div>
    </header>

    <section id="funciones">
        <div class="wrap">
            <div class="eyebrow">Todo en un solo lugar</div>
            <h2>Tu negocio de reparto, ordenado y automatizado</h2>
            <p class="lead">Desde la conversación con el cliente hasta el cobro y el inventario, GasAI cubre el día a día de tu operación.</p>

            <div class="grid">
                <div class="feat"><div class="ic">💬</div><h3>Asistente por WhatsApp</h3><p>Responde preguntas, arma el pedido con tus productos y precios reales, y coordina día y hora de entrega. Tú tomas el control cuando quieras.</p></div>
                <div class="feat"><div class="ic">🚚</div><h3>Tablero de despacho</h3><p>Pedidos por estado (pendiente, en ruta, entregado), asignación de repartidor y avance con un clic.</p></div>
                <div class="feat"><div class="ic">🧮</div><h3>Punto de venta</h3><p>Cobra en mostrador o cierra un pedido, con precios editables y ticket imprimible (80/58 mm).</p></div>
                <div class="feat"><div class="ic">💵</div><h3>Caja y reportes</h3><p>Apertura y cierre de turno, arqueo, y un escritorio con ventas del día, del mes y tendencias.</p></div>
                <div class="feat"><div class="ic">📦</div><h3>Inventario</h3><p>Existencias por sucursal, alertas de stock bajo y bloqueo de entregas sin stock.</p></div>
                <div class="feat"><div class="ic">👥</div><h3>Multi-negocio y equipo</h3><p>Roles para dueños, operadores y repartidores; cada negocio con su propio espacio.</p></div>
            </div>
        </div>
    </section>

    <section style="background:#f1f5f9;">
        <div class="wrap">
            <div class="eyebrow">En 3 pasos</div>
            <h2>Cómo funciona</h2>
            <div class="steps">
                <div class="step"><div class="n">1</div><h3>Configura tu negocio</h3><p>Carga productos, zonas de entrega y el conocimiento de tu empresa en minutos.</p></div>
                <div class="step"><div class="n">2</div><h3>Conecta WhatsApp</h3><p>Vincula tu número y deja que el asistente atienda a tus clientes automáticamente.</p></div>
                <div class="step"><div class="n">3</div><h3>Vende y despacha</h3><p>Recibe pedidos, cobra, entrega y lleva el control de caja e inventario desde el panel.</p></div>
            </div>
        </div>
    </section>

    <section>
        <div class="wrap">
            <div class="cta-band">
                <h2>Lleva tu reparto al siguiente nivel</h2>
                <p>Atiende más rápido, no pierdas pedidos y ten todo tu negocio en orden.</p>
                <a class="btn ghost" href="/admin" style="background:#fff;">Ingresar al panel</a>
            </div>
        </div>
    </section>

    <footer>
        <div class="wrap row">
            <span>© {{ date('Y') }} GasAI · tandix.app</span>
            <span>
                <a href="/privacidad">Privacidad</a> ·
                <a href="/terminos">Términos</a> ·
                <a href="/eliminacion-datos">Eliminación de datos</a> ·
                <a href="mailto:soporte@tandix.app">soporte@tandix.app</a>
            </span>
        </div>
    </footer>
</body>
</html>
