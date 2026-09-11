<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GasAI · Cada chat de WhatsApp, convertido en un pedido</title>
    <meta name="description" content="GasAI atiende a tus clientes por WhatsApp, arma el pedido con tus precios y coordina la entrega. Panel de despacho, ventas, caja e inventario. Hecho para distribuidoras de agua y gas.">
    <meta name="robots" content="index,follow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            --paper: #eef3f4;
            --surface: #ffffff;
            --ink: #06272e;
            --ink-soft: #3d5a61;
            --aqua: #0891a6;
            --aqua-bright: #06b6d4;
            --deep: #0b4a57;
            --amber: #f59e0b;
            --amber-deep: #d97706;
            --wa: #1fab54;
            --line: #d8e4e6;
            --shadow-lg: 0 24px 60px -28px rgba(6,39,46,.35);
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0; background: var(--paper); color: var(--ink);
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6; -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; text-decoration: none; }
        .wrap { width: 100%; max-width: 1120px; margin: 0 auto; padding-inline: 22px; }
        .display { font-family: "Bricolage Grotesque", "Inter", sans-serif; font-weight: 800; letter-spacing: -.02em; line-height: 1.03; }

        .btn { display: inline-flex; align-items: center; gap: .5rem; font-weight: 700; font-size: .98rem;
            padding: .78rem 1.35rem; border-radius: .8rem; border: 1.5px solid transparent; cursor: pointer; transition: transform .12s ease, box-shadow .12s ease; }
        .btn.solid { background: var(--amber); color: #2a1a02; box-shadow: 0 8px 22px -8px rgba(245,158,11,.6); }
        .btn.solid:hover { transform: translateY(-1px); box-shadow: 0 12px 28px -8px rgba(245,158,11,.7); }
        .btn.line { background: transparent; color: var(--ink); border-color: var(--line); }
        .btn.line:hover { border-color: var(--aqua); color: var(--aqua); }
        .btn.on-dark { background: #fff; color: var(--ink); }

        /* ---------- Nav ---------- */
        .nav { position: sticky; top: 0; z-index: 30; background: color-mix(in srgb, var(--paper) 82%, transparent); backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); }
        .nav .row { display: flex; align-items: center; justify-content: space-between; height: 68px; }
        .brand { display: flex; align-items: center; gap: 11px; font-weight: 800; font-size: 1.28rem; }
        .brand .mark { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; color: #fff; font-size: 1.15rem;
            background: linear-gradient(150deg, var(--aqua-bright), var(--deep)); box-shadow: 0 6px 16px -6px rgba(8,145,166,.7); }
        .brand small { font-weight: 500; font-size: .74rem; color: var(--ink-soft); display: block; margin-top: -3px; }

        /* ---------- Hero ---------- */
        .hero { padding: 68px 0 40px; }
        .hero-grid { display: grid; grid-template-columns: 1.05fr .95fr; gap: 54px; align-items: center; }
        .tagline { display: inline-flex; align-items: center; gap: .5rem; font-size: .82rem; font-weight: 600; color: var(--deep);
            background: #fff; border: 1px solid var(--line); border-radius: 999px; padding: .35rem .8rem; margin-bottom: 1.3rem; }
        .tagline .pulse { width: 8px; height: 8px; border-radius: 50%; background: var(--wa); box-shadow: 0 0 0 4px rgba(31,171,84,.18); }
        h1.hero-title { font-size: clamp(2.5rem, 5.6vw, 4.15rem); margin: 0 0 1rem; }
        h1.hero-title em { font-style: normal; color: var(--aqua); position: relative; white-space: nowrap; }
        h1.hero-title em::after { content: ""; position: absolute; left: 0; right: 0; bottom: .06em; height: .18em; border-radius: 3px;
            background: linear-gradient(90deg, rgba(6,182,212,.35), rgba(245,158,11,.45)); z-index: -1; }
        .hero p.sub { font-size: 1.18rem; color: var(--ink-soft); max-width: 46ch; margin: 0 0 1.7rem; }
        .hero .actions { display: flex; gap: .8rem; flex-wrap: wrap; }
        .hero .under { margin-top: 1.5rem; font-size: .9rem; color: var(--ink-soft); display: flex; align-items: center; gap: .5rem; }
        .hero .under b { color: var(--ink); font-weight: 700; }

        /* ---------- Chat mockup ---------- */
        .phone { background: #0c3942; border-radius: 30px; padding: 12px; box-shadow: var(--shadow-lg); border: 1px solid #0a2e35; }
        .chat { border-radius: 20px; overflow: hidden; background:
            linear-gradient(#e6ebe6, #e6ebe6) padding-box,
            radial-gradient(circle at 20% 30%, rgba(11,74,87,.05), transparent 40%); }
        .chat-head { display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: #0e5561; color: #eafcff; }
        .chat-head .av { width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center;
            background: linear-gradient(150deg, var(--aqua-bright), #22d3ee); }
        .chat-head .nm { font-weight: 700; font-size: .95rem; line-height: 1.1; }
        .chat-head .st { font-size: .72rem; color: #a8e6ef; display: flex; align-items: center; gap: .3rem; }
        .chat-head .st .g { width: 6px; height: 6px; border-radius: 50%; background: #4ade80; }
        .chat-body { padding: 16px 14px; display: flex; flex-direction: column; gap: 9px;
            background-color: #dfe7e2;
            background-image: radial-gradient(rgba(11,74,87,.05) 1px, transparent 1px); background-size: 18px 18px; }
        .bub { max-width: 82%; padding: 8px 11px; border-radius: 14px; font-size: .9rem; line-height: 1.35; color: #0b1f22;
            box-shadow: 0 1px 1px rgba(0,0,0,.08); position: relative; opacity: 0; transform: translateY(8px); animation: pop .5s ease forwards; }
        .bub.in { align-self: flex-start; background: #fff; border-bottom-left-radius: 4px; }
        .bub.out { align-self: flex-end; background: #d6f5c8; border-bottom-right-radius: 4px; }
        .bub .t { font-size: .62rem; color: #5a7a6b; float: right; margin: 4px 0 -2px 8px; }
        .bub:nth-child(1){animation-delay:.15s} .bub:nth-child(2){animation-delay:.5s}
        .bub:nth-child(3){animation-delay:.9s} .bub:nth-child(4){animation-delay:1.35s}
        .bub.pill { align-self: center; background: rgba(255,255,255,.75); color: #0e5561; font-size: .72rem; font-weight: 600;
            border-radius: 999px; padding: 4px 12px; box-shadow: none; }
        @keyframes pop { to { opacity: 1; transform: translateY(0); } }
        @media (prefers-reduced-motion: reduce) { .bub { animation: none; opacity: 1; transform: none; } }

        /* ---------- Bento pilares ---------- */
        section { padding: 62px 0; }
        .kicker { font-family: "Bricolage Grotesque", sans-serif; font-weight: 700; color: var(--aqua); font-size: .95rem; margin: 0 0 .4rem; }
        h2.sec { font-size: clamp(1.7rem, 3.4vw, 2.4rem); margin: 0 0 .5rem; }
        .sec-lead { color: var(--ink-soft); font-size: 1.06rem; max-width: 58ch; margin: 0; }

        .bento { display: grid; grid-template-columns: repeat(12, 1fr); gap: 16px; margin-top: 34px; }
        .cell { border-radius: 20px; padding: 26px; border: 1px solid var(--line); background: var(--surface); position: relative; overflow: hidden; }
        .cell .ic { width: 46px; height: 46px; border-radius: 13px; display: grid; place-items: center; font-size: 1.35rem; margin-bottom: 14px; }
        .cell h3 { margin: 0 0 6px; font-size: 1.2rem; font-family: "Bricolage Grotesque", sans-serif; font-weight: 700; letter-spacing: -.01em; }
        .cell p { margin: 0; color: var(--ink-soft); font-size: .96rem; }
        .cell .mini { margin-top: 14px; display: flex; flex-wrap: wrap; gap: 6px; }
        .cell .mini span { font-size: .78rem; background: rgba(11,74,87,.06); color: var(--deep); border-radius: 999px; padding: .2rem .6rem; }
        .cell.wide { grid-column: span 7; }
        .cell.slim { grid-column: span 5; }
        .cell.feature { grid-column: span 7; color: #eafcff; border: 0;
            background: radial-gradient(120% 140% at 10% 0%, #0e5561, #06272e 70%); }
        .cell.feature h3 { color: #fff; } .cell.feature p { color: #a8cdd4; }
        .cell.feature .ic { background: rgba(255,255,255,.12); }
        .cell.feature .mini span { background: rgba(255,255,255,.1); color: #cdeef3; }
        .cell.a .ic { background: #e6faff; } .cell.b .ic { background: #fff2dd; } .cell.c .ic { background: #eafbef; } .cell.d .ic { background: #eef2ff; }
        @media (max-width: 820px) { .cell.wide, .cell.slim, .cell.feature { grid-column: span 12; } }

        /* ---------- Pasos ---------- */
        .steps-band { background: linear-gradient(180deg, #06272e, #0b4a57); color: #eafcff; }
        .steps-band .kicker { color: #5fd7e6; }
        .steps-band h2.sec { color: #fff; }
        .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-top: 34px; counter-reset: s; }
        .step { position: relative; padding-top: 22px; }
        .step::before { counter-increment: s; content: "0" counter(s); font-family: "Bricolage Grotesque", sans-serif; font-weight: 800;
            font-size: 2.4rem; color: transparent; -webkit-text-stroke: 1.5px rgba(95,215,230,.55); display: block; margin-bottom: 6px; }
        .step h3 { margin: 0 0 6px; color: #fff; font-size: 1.15rem; font-family: "Bricolage Grotesque", sans-serif; }
        .step p { margin: 0; color: #9fc6cd; font-size: .96rem; }
        @media (max-width: 760px) { .steps { grid-template-columns: 1fr; } }

        /* ---------- Cierre ---------- */
        .close { text-align: center; }
        .close .card { background: linear-gradient(140deg, var(--aqua-bright), var(--deep) 90%); color: #fff; border-radius: 28px;
            padding: 54px 30px; box-shadow: var(--shadow-lg); }
        .close h2 { font-family: "Bricolage Grotesque", sans-serif; font-size: clamp(1.8rem, 4vw, 2.6rem); font-weight: 800; margin: 0 0 .5rem; letter-spacing: -.02em; }
        .close p { color: rgba(255,255,255,.9); max-width: 46ch; margin: 0 auto 1.5rem; font-size: 1.06rem; }

        footer { border-top: 1px solid var(--line); padding: 34px 0; color: var(--ink-soft); font-size: .9rem; }
        footer .row { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 14px; align-items: center; }
        footer nav a { margin-left: 16px; } footer nav a:hover { color: var(--ink); }
        @media (max-width: 620px) { .hero-grid { grid-template-columns: 1fr; gap: 36px; } footer nav a { margin: 0 12px 0 0; } }
    </style>
</head>
<body>
    <header class="nav">
        <div class="wrap row">
            <span class="brand"><span class="mark">💧</span> <span>GasAI<small>por Myagendo</small></span></span>
            <a class="btn solid" href="/admin">Ingresar</a>
        </div>
    </header>

    <!-- HERO -->
    <section class="hero">
        <div class="wrap hero-grid">
            <div>
                <span class="tagline"><span class="pulse"></span> Asistente de WhatsApp para tu reparto</span>
                <h1 class="display hero-title">Cada chat de WhatsApp, convertido en un <em>pedido</em>.</h1>
                <p class="sub">GasAI atiende a tus clientes, arma el pedido con tus precios reales y coordina la entrega. Tú manejas despacho, ventas, caja e inventario desde un solo panel.</p>
                <div class="actions">
                    <a class="btn solid" href="/admin">Ingresar al panel</a>
                    <a class="btn line" href="#producto">Ver cómo funciona</a>
                </div>
                <div class="under">💧🔥 <span>Hecho para distribuidoras de <b>agua</b> y <b>gas</b> en Perú.</span></div>
            </div>

            <!-- Chat mockup: lo más característico del producto -->
            <div class="phone" aria-hidden="true">
                <div class="chat">
                    <div class="chat-head">
                        <span class="av">💧</span>
                        <div><div class="nm">Manu · H2O Wanka</div><div class="st"><span class="g"></span> en línea</div></div>
                    </div>
                    <div class="chat-body">
                        <div class="bub pill">Hoy</div>
                        <div class="bub in">Hola, quiero 2 bidones de 20L para hoy en la tarde 🙏 <span class="t">3:04</span></div>
                        <div class="bub out">¡Claro! 2 bidones de 20L = S/ 50. ¿Te los llevo a Av. Los Incas 456, El Tambo? Tenemos reparto de 2 a 6 pm. <span class="t">3:04</span></div>
                        <div class="bub in">Sí, a esa dirección. Pago en efectivo 👍 <span class="t">3:05</span></div>
                        <div class="bub out">¡Perfecto! Pedido confirmado para hoy 2–6 pm. Tu repartidor va en camino 🚚 <span class="t">3:05</span></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- PRODUCTO / BENTO -->
    <section id="producto">
        <div class="wrap">
            <p class="kicker">Todo tu negocio, en un panel</p>
            <h2 class="sec display">De la conversación a la entrega, sin perder pedidos</h2>
            <p class="sec-lead">El asistente conversa y vende; tú tienes el control de la operación completa, con la información siempre al día.</p>

            <div class="bento">
                <div class="cell feature">
                    <div class="ic">💬</div>
                    <h3>Un asistente que atiende y vende, 24/7</h3>
                    <p>Responde dudas, arma el pedido con tus productos y precios reales, propone día y hora de entrega y confirma. Cuando quieras, tomas el control del chat tú mismo.</p>
                    <div class="mini"><span>Toma pedidos</span><span>Reconoce al cliente</span><span>Reparto por zonas</span><span>Handoff a humano</span></div>
                </div>
                <div class="cell slim a">
                    <div class="ic">🚚</div>
                    <h3>Despacho y entregas</h3>
                    <p>Tablero por estado, asignación de repartidor y avance con un clic hasta “entregado”.</p>
                </div>
                <div class="cell slim b">
                    <div class="ic">🧮</div>
                    <h3>Punto de venta y caja</h3>
                    <p>Cobra en mostrador o cierra un pedido, con precio editable, ticket 80/58 mm y arqueo de caja.</p>
                </div>
                <div class="cell wide c">
                    <div class="ic">📦</div>
                    <h3>Inventario que te cuida</h3>
                    <p>Existencias por sucursal, alertas de stock bajo y bloqueo de entregas cuando no hay stock, para que nunca vendas lo que no tienes.</p>
                    <div class="mini"><span>Stock por sucursal</span><span>Alertas</span><span>Movimientos</span></div>
                </div>
                <div class="cell slim d">
                    <div class="ic">📈</div>
                    <h3>Reportes y multi-negocio</h3>
                    <p>Ventas del día y del mes, top productos y roles para dueños, operadores y repartidores.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- PASOS -->
    <section class="steps-band">
        <div class="wrap">
            <p class="kicker">Empieza rápido</p>
            <h2 class="sec display">Listo en tres pasos</h2>
            <div class="steps">
                <div class="step"><h3>Configura tu negocio</h3><p>Carga tus productos, zonas de entrega y el conocimiento de tu empresa. Toma unos minutos.</p></div>
                <div class="step"><h3>Conecta tu WhatsApp</h3><p>Vincula tu número y deja que el asistente empiece a atender a tus clientes.</p></div>
                <div class="step"><h3>Vende y despacha</h3><p>Recibe pedidos, cobra, entrega y controla caja e inventario desde el panel.</p></div>
            </div>
        </div>
    </section>

    <!-- CIERRE -->
    <section class="close">
        <div class="wrap">
            <div class="card">
                <h2>Atiende más, pierde menos, ordena todo.</h2>
                <p>Pon a trabajar un asistente por WhatsApp y ten tu reparto bajo control.</p>
                <a class="btn on-dark" href="/admin">Ingresar al panel</a>
            </div>
        </div>
    </section>

    <footer>
        <div class="wrap row">
            <span>© {{ date('Y') }} GasAI — un producto de <b style="color:var(--ink);">Myagendo</b> · tandix.app</span>
            <nav>
                <a href="/privacidad">Privacidad</a>
                <a href="/terminos">Términos</a>
                <a href="/eliminacion-datos">Eliminación de datos</a>
                <a href="mailto:soporte@tandix.app">soporte@tandix.app</a>
            </nav>
        </div>
    </footer>
</body>
</html>
