@extends('legal.layout')

@section('title', 'Política de Privacidad')

@section('content')
    <h1>Política de Privacidad</h1>
    <p class="updated">Última actualización: {{ date('d/m/Y') }}</p>

    <p>
        GasAI (operado a través de <strong>tandix.app</strong>) es una plataforma que permite a negocios de
        reparto (agua, gas y similares) atender a sus clientes y gestionar pedidos, incluyendo un asistente
        automatizado que conversa por WhatsApp. Esta política explica qué datos tratamos y cómo los protegemos.
    </p>

    <h2>1. Datos que tratamos</h2>
    <ul>
        <li><strong>Del negocio:</strong> nombre, rubro, sucursales, productos, precios, zonas de entrega y datos de acceso.</li>
        <li><strong>De los clientes del negocio:</strong> número de teléfono/identificador de WhatsApp, nombre, direcciones de entrega y el contenido de los mensajes enviados al chat.</li>
        <li><strong>Operativos:</strong> pedidos, ventas, movimientos de caja e inventario que el negocio registra.</li>
        <li><strong>Técnicos:</strong> registros de uso necesarios para operar y dar soporte al servicio.</li>
    </ul>

    <h2>2. Para qué usamos los datos</h2>
    <ul>
        <li>Prestar el servicio: recibir y responder mensajes, tomar pedidos y gestionar entregas y cobros.</li>
        <li>Generar respuestas del asistente automatizado en base a la información del negocio.</li>
        <li>Dar soporte, mejorar el servicio y garantizar su seguridad.</li>
    </ul>

    <h2>3. Con quién se comparten</h2>
    <p>No vendemos datos personales. Los compartimos únicamente con proveedores que hacen funcionar el servicio:</p>
    <ul>
        <li><strong>Meta / WhatsApp Business Platform</strong>, para enviar y recibir los mensajes.</li>
        <li><strong>Proveedores de infraestructura y de modelos de lenguaje</strong> que procesan los mensajes para generar respuestas del asistente.</li>
    </ul>
    <p>Cada negocio es responsable del trato que da a los datos de sus propios clientes dentro de la plataforma.</p>

    <h2>4. Conservación</h2>
    <p>
        Conservamos los datos mientras la cuenta del negocio esté activa y el tiempo necesario para cumplir
        obligaciones legales. Luego se eliminan o anonimizan.
    </p>

    <h2>5. Seguridad</h2>
    <p>
        Aplicamos medidas razonables para proteger la información (cifrado en tránsito, control de acceso y
        almacenamiento cifrado de credenciales sensibles). Ningún sistema es 100% infalible.
    </p>

    <h2>6. Tus derechos</h2>
    <p>
        Puedes solicitar acceso, corrección o eliminación de tus datos escribiendo a
        <a href="mailto:soporte@tandix.app">soporte@tandix.app</a>. Consulta también nuestra
        <a href="/eliminacion-datos">instrucción de eliminación de datos</a>.
    </p>

    <h2>7. Cambios</h2>
    <p>Podemos actualizar esta política; publicaremos la versión vigente en esta misma página.</p>

    <h2>8. Contacto</h2>
    <p>Responsable: GasAI (tandix.app). Correo: <a href="mailto:soporte@tandix.app">soporte@tandix.app</a>.</p>
@endsection
