@extends('legal.layout')

@section('title', 'Términos de Servicio')

@section('content')
    <h1>Términos de Servicio</h1>
    <p class="updated">Última actualización: {{ date('d/m/Y') }}</p>

    <p>
        Estos términos rigen el uso de GasAI (tandix.app), la plataforma que permite a negocios de reparto
        gestionar pedidos y atender clientes, incluyendo un asistente automatizado por WhatsApp. Al usar el
        servicio, aceptas estos términos.
    </p>

    <h2>1. El servicio</h2>
    <p>
        GasAI ofrece herramientas de atención y gestión (chat con asistente, tablero de despacho, punto de venta,
        caja, inventario y reportes). El servicio se provee “tal cual” y puede evolucionar con el tiempo.
    </p>

    <h2>2. Cuentas y acceso</h2>
    <ul>
        <li>Cada negocio es responsable de la veracidad de sus datos y de la actividad de su cuenta.</li>
        <li>Debes resguardar tus credenciales y notificar cualquier uso no autorizado.</li>
    </ul>

    <h2>3. Uso aceptable</h2>
    <ul>
        <li>No usar el servicio para fines ilícitos, spam o envío de mensajes no solicitados.</li>
        <li>Cumplir las políticas de WhatsApp/Meta y la normativa aplicable de protección de datos.</li>
        <li>No intentar vulnerar la seguridad ni interferir con el funcionamiento de la plataforma.</li>
    </ul>

    <h2>4. Contenido y datos de tus clientes</h2>
    <p>
        El negocio es responsable de los datos de sus clientes que registra o recibe a través de la plataforma,
        y de contar con las bases legales para tratarlos. GasAI actúa como proveedor tecnológico.
    </p>

    <h2>5. Disponibilidad</h2>
    <p>
        Procuramos alta disponibilidad, pero el servicio puede tener interrupciones por mantenimiento o causas
        ajenas (incluidos servicios de terceros como WhatsApp/Meta).
    </p>

    <h2>6. Limitación de responsabilidad</h2>
    <p>
        En la medida permitida por la ley, GasAI no será responsable por daños indirectos o lucro cesante
        derivados del uso o la imposibilidad de uso del servicio.
    </p>

    <h2>7. Terminación</h2>
    <p>
        Puedes dejar de usar el servicio cuando quieras. Podemos suspender cuentas que incumplan estos términos.
    </p>

    <h2>8. Cambios</h2>
    <p>Podemos actualizar estos términos; la versión vigente estará siempre en esta página.</p>

    <h2>9. Ley aplicable y contacto</h2>
    <p>
        Estos términos se rigen por las leyes de la República del Perú. Contacto:
        <a href="mailto:soporte@tandix.app">soporte@tandix.app</a>.
    </p>
@endsection
