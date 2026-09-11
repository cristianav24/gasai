<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · GasAI</title>
    <meta name="robots" content="index,follow">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: #f1f5f9; color: #0f172a;
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            line-height: 1.65;
        }
        .wrap { max-width: 780px; margin: 0 auto; padding: 0 20px 64px; }

        header.top {
            background: linear-gradient(135deg, #0ea5e9 0%, #06b6d4 45%, #f59e0b 130%);
            color: #fff; padding: 28px 0;
        }
        header.top .inner { max-width: 780px; margin: 0 auto; padding: 0 20px; display: flex; align-items: center; gap: 12px; }
        header.top .brand { font-weight: 800; font-size: 1.25rem; letter-spacing: -.01em; }
        header.top .brand small { display: block; font-weight: 500; font-size: .8rem; opacity: .9; }

        .card {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; margin-top: -24px;
            box-shadow: 0 10px 40px -16px rgba(15,23,42,.18); padding: 32px 34px;
        }
        h1 { font-size: 1.7rem; font-weight: 800; margin: 0 0 4px; letter-spacing: -.02em; }
        .updated { color: #64748b; font-size: .85rem; margin: 0 0 24px; }
        h2 { font-size: 1.12rem; font-weight: 700; margin: 28px 0 8px; }
        p, li { color: #1e293b; font-size: .96rem; }
        ul { padding-left: 20px; }
        li { margin: 4px 0; }
        a { color: #0284c7; }
        .muted { color: #64748b; font-size: .88rem; }
        footer.foot { text-align: center; color: #64748b; font-size: .82rem; margin-top: 28px; }
        footer.foot a { color: #64748b; margin: 0 8px; }
    </style>
</head>
<body>
    <header class="top">
        <div class="inner">
            <img src="/favicon.svg" alt="GasAI" style="width:34px;height:34px;border-radius:9px;display:block;">
            <span class="brand">GasAI<small>tandix.app · plataforma de pedidos por WhatsApp</small></span>
        </div>
    </header>

    <div class="wrap">
        <div class="card">
            @yield('content')
        </div>

        <footer class="foot">
            <a href="/privacidad">Privacidad</a> ·
            <a href="/terminos">Términos</a> ·
            <a href="/eliminacion-datos">Eliminación de datos</a>
            <div style="margin-top:8px;">© {{ date('Y') }} GasAI · Contacto: <a href="mailto:soporte@tandix.app">soporte@tandix.app</a></div>
        </footer>
    </div>
</body>
</html>
