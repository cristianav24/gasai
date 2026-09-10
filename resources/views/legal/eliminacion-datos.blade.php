@extends('legal.layout')

@section('title', 'Eliminación de Datos')

@section('content')
    <h1>Instrucciones para la eliminación de datos</h1>
    <p class="updated">Última actualización: {{ date('d/m/Y') }}</p>

    <p>
        En GasAI (tandix.app) puedes solicitar la eliminación de los datos personales asociados a tu cuenta o a
        tu interacción con un negocio que usa nuestra plataforma.
    </p>

    <h2>Cómo solicitarlo</h2>
    <ul>
        <li>
            Escríbenos a <a href="mailto:soporte@tandix.app">soporte@tandix.app</a> desde el correo asociado a tu
            cuenta, o indicando el número de teléfono con el que escribiste al negocio por WhatsApp.
        </li>
        <li>Incluye en el asunto “Eliminación de datos”.</li>
        <li>Verificaremos tu identidad y procesaremos la solicitud en un plazo máximo de 30 días.</li>
    </ul>

    <h2>Qué se elimina</h2>
    <p>
        Eliminamos o anonimizamos los datos personales que tratamos (por ejemplo, número de contacto, nombre,
        direcciones y el historial de mensajes), salvo aquello que debamos conservar por obligaciones legales o
        contables (por ejemplo, comprobantes de venta), que se conserva solo por el tiempo exigido por ley.
    </p>

    <h2>Clientes de un negocio</h2>
    <p>
        Si interactuaste con un negocio que usa GasAI, también puedes pedirle directamente a ese negocio la
        eliminación de tus datos. Nosotros los asistiremos para cumplir tu solicitud.
    </p>

    <h2>Contacto</h2>
    <p>Correo de contacto: <a href="mailto:soporte@tandix.app">soporte@tandix.app</a>.</p>
@endsection
