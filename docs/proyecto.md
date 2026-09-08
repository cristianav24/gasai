# Prompt para Claude Code — SaaS "Agente de pedidos por WhatsApp"


## Contexto del proyecto

Voy a construir desde cero un SaaS multi-tenant en Laravel para negocios de reparto a domicilio en Perú (agua embotellada, gas doméstico, etc.).

El producto tiene dos mitades que hoy tengo separadas y quiero unificar:

1. **Un agente conversacional de WhatsApp** que atiende al cliente final, resuelve dudas con el conocimiento de la empresa, arma el pedido y captura los datos de entrega (nombre, teléfono, dirección exacta, referencia).
2. **Un sistema de despacho** donde el dueño y los repartidores ven los pedidos agendados, su estado y a quién le toca entregarlos.

Mi propio negocio de agua (H2O Wanka, Huancayo) será el tenant #1, pero el sistema debe ser genérico y vendible a otros negocios de reparto.

**Diferenciador clave:** el bot no es un árbol de respuestas. Es un agente con herramientas: reconoce al cliente por su número, recuerda sus direcciones anteriores, consulta precios reales de la base de datos y crea el pedido él mismo. Si el cliente ya pidió antes, no vuelve a pedirle todos los datos.

## Stack obligatorio

- Laravel 12 (proyecto nuevo, no reutilizo código anterior)
- Filament 4 para el panel de administración
- PostgreSQL
- Livewire para lo que no sea Filament
- Colas con Redis + Horizon
- Deploy final en VPS Ubuntu (Contabo), subdominio por tenant

## Decisiones ya tomadas (no las cuestiones, ejecútalas)

- **LLM: DeepSeek** vía su API compatible con OpenAI (function calling estilo OpenAI). Encapsúlalo detrás de una interfaz `LlmProvider` para poder cambiar de proveedor después sin tocar el agente.
- **Multi-tenancy:** base de datos única, columna `tenant_id`, global scope obligatorio en todos los modelos de negocio. Usa la tenancy nativa de Filament con el modelo `Tenant`.
- **WhatsApp en el MVP:** conexión manual — el dueño pega su `phone_number_id`, `waba_id` y access token permanente en el panel. El Embedded Signup de Meta viene después, así que aísla la conexión detrás de un servicio para no reescribir todo luego.
- **Knowledge base:** por ahora texto plano inyectado en el system prompt (sin embeddings ni base vectorial). Limita el tamaño total y avísame si se pasa.

## Modelo de datos (punto de partida, propón mejoras antes de migrar)

- `tenants` — nombre, slug, rubro, moneda, zona horaria, horario de atención
- `users` — pertenecen a uno o varios tenants; roles: dueño, operador, repartidor
- `products` — nombre, descripción, precio, unidad, tipo (venta nueva / recarga), activo
- `delivery_zones` — nombre, costo de envío, cobertura descrita en texto
- `customers` — teléfono en formato E.164 (único por tenant), nombre, notas
- `addresses` — pertenece a customer, texto, referencia, lat/lng opcional, principal
- `orders` — customer, address, items, total, estado (nuevo / confirmado / en_ruta / entregado / cancelado), canal (whatsapp / playground / manual), fecha programada, repartidor asignado, notas
- `order_items` — producto, cantidad, precio unitario congelado
- `conversations` — customer o contacto anónimo, estado (bot / humano), última actividad
- `messages` — rol (user / assistant / tool), contenido, id de mensaje de WhatsApp, payload crudo en JSON
- `knowledge_items` — título, contenido, activo
- `bot_configs` — nombre del agente, tono, instrucciones extra, mensaje de bienvenida, temperatura
- `whatsapp_accounts` — phone_number_id, waba_id, access token (cast `encrypted`), estado de conexión

## El agente

`AgentService` recibe (tenant, conversación, mensaje entrante) y devuelve la respuesta.

**System prompt** = plantilla base + `bot_config` + `knowledge_items` activos + catálogo de productos con precios + zonas de entrega + contexto del cliente si ya se identificó.

**Herramientas (function calling):**

- `buscar_cliente(telefono)`
- `guardar_cliente(nombre, telefono)`
- `listar_direcciones(cliente_id)` / `guardar_direccion(...)`
- `listar_productos()`
- `calcular_total(items, zona)`
- `crear_pedido(cliente_id, direccion_id, items, fecha_programada, notas)`
- `estado_pedido(pedido_id)`
- `escalar_a_humano(motivo)`

**Reglas duras:**

- El `tenant_id` de toda herramienta sale del contexto del servidor, **nunca** de un argumento que mande el modelo.
- Los precios y totales los calcula el código, no el LLM. El modelo solo los comunica.
- Máximo de iteraciones de tool calls por turno (por ejemplo 6) y timeout; si se pasa, escala a humano.
- Cada mensaje entrante se procesa en un job en cola, con un debounce de ~5 segundos para agrupar mensajes seguidos del mismo contacto.
- Nunca inventes disponibilidad, cobertura ni tiempos de entrega que no estén en la base de datos.
- Al agendar, el agente pide **fecha y franja horaria** (mañana / tarde / hora exacta). Un pedido no debe quedar guardado a las 00:00 porque solo se capturó la fecha.

## Onboarding guiado (wizard)

Un dueño de negocio de agua o gas no sabe usar un panel de administración. Cuando un tenant nuevo entra por primera vez, no lo mandes al dashboard vacío: llévalo por un **onboarding wizard** paso a paso.

Pasos del wizard:

1. **Tu negocio** — nombre, rubro, horario de atención, zona horaria.
2. **Tus productos** — que cargue al menos uno, con ejemplos precargados según el rubro elegido (ej. agua: "Bidón 20L nuevo", "Recarga 20L"; gas: "Balón 10kg").
3. **Zonas de entrega** — dónde reparte y cuánto cobra por el envío.
4. **Conocimiento de tu empresa** — un textarea guiado con preguntas concretas ("¿en cuánto tiempo entregas?", "¿aceptas Yape o Plin?", "¿qué pasa si el cliente no está?") en lugar de una caja en blanco.
5. **Tu agente** — nombre del bot, tono, mensaje de bienvenida.
6. **Pruébalo** — lo suelta directo en el playground para que converse con su propio agente antes de conectar nada.
7. **Conecta tu WhatsApp** — este paso se puede saltar y retomar después.

Requisitos:

- Guarda el progreso en el tenant (`onboarding_step`, `onboarding_completed_at`). Si el usuario cierra sesión a mitad, vuelve donde se quedó.
- Todo paso debe poder saltarse salvo "Tu negocio". El wizard nunca debe ser una cárcel.
- Al terminar, muestra en el dashboard un **checklist de progreso** con lo que quedó pendiente (ej. "Falta conectar tu WhatsApp"), y que se pueda descartar.
- Cada pantalla del wizard con un texto corto que explique *para qué sirve* ese paso, en lenguaje de dueño de negocio, no de programador.
- Usa el componente Wizard de Filament; nada de librerías externas de product tour.

## POS, caja y stock

El pedido no termina cuando el agente lo agenda: termina cuando alguien lo cobra. El sistema incluye un **POS** al que se entra desde el pedido o como venta directa de mostrador.

- El precio del producto es **referencial**. En el POS se puede editar el precio unitario, aplicar descuento y agregar una nota. Guarda siempre `unit_price_list` y `unit_price_charged` por línea: los reportes de margen dependen de esa diferencia.
- **El LLM nunca edita precios.** El agente cotiza con el precio de lista; el ajuste es una acción humana en el POS y queda registrada con el usuario que la hizo.
- Al cobrar desde un pedido, el cliente del pedido debe venir **preseleccionado** en el POS. Nunca dejar caer la venta en "Mostrador (sin cliente)" si el pedido tenía cliente.
- Métodos de pago configurables por tenant (efectivo, Yape, Plin, transferencia, crédito). El agente debe poder responder qué acepta el negocio.
- **Caja:** apertura y cierre de turno, arqueo, movimientos de entrada y salida.
- Estados de venta: cobrada, en espera, cancelada.

**Sucursales e inventario:**

- Dentro de un tenant puede haber varias sucursales o almacenes (ej. "Central"). Todo pedido, venta, stock y caja pertenece a una sucursal. Ojo: esto es una capa **distinta** del multi-tenant.
- El stock se descuenta **al entregar**, no al anotar el pedido.
- Transferencias de stock entre sucursales.

**Envases retornables (crítico para agua y gas):**

- Lleva el saldo de envases en poder de cada cliente (bidones o balones prestados vs devueltos).
- Diferencia "venta de envase nuevo" de "recarga": la recarga exige que el cliente entregue el envase vacío.
- El agente debe poder consultar ese saldo y avisarle al cliente ("tienes 2 bidones nuestros").

## Fases de trabajo

Trabaja **una fase a la vez**. Al terminar cada una, párate, muéstrame qué hiciste y espera mi visto bueno antes de seguir.

1. **Fundaciones** — proyecto, Postgres, Filament con tenancy, autenticación, roles, migraciones y modelos base con sus global scopes.
2. **Configuración del negocio** — CRUD en Filament de productos, zonas de entrega, knowledge base y configuración del bot.
3. **Agente + playground** — `LlmProvider`, `AgentService`, las herramientas, y una pantalla de chat dentro del panel para probar al agente sin WhatsApp. Esta fase debe funcionar completa antes de tocar WhatsApp.
4. **Onboarding wizard** — el asistente guiado descrito arriba, más el checklist de progreso en el dashboard. Va después del playground porque el paso "Pruébalo" lo necesita.
5. **WhatsApp Cloud API** — pantalla de conexión manual, webhook único que resuelve el tenant por `phone_number_id`, recepción y envío de mensajes, manejo de la ventana de 24 horas.
6. **Despacho** — tablero de pedidos por estado (pendiente / sale a reparto / entregado), asignación de repartidor, vista móvil simple para el repartidor y notificación push al dispositivo cuando entra un pedido nuevo del agente.
7. **POS, caja y stock** — cobro con precio editable, métodos de pago, apertura y cierre de caja, descuento de inventario al entregar y transferencias entre sucursales.
8. **Envases retornables** — saldo de bidones o balones por cliente, y la herramienta para que el agente lo consulte.
9. **Handoff humano** — bandeja de conversaciones, botón para que un operador tome el control y devuelva el hilo al agente.

## Cómo quiero que trabajes

- Español en la interfaz y en los comentarios; inglés en nombres de código.
- Antes de escribir código en cada fase, muéstrame el plan en pocas líneas y espera confirmación.
- Pasos pequeños. Prefiero revisar seguido a recibir mil líneas de golpe.
- Tests de feature para el agente y las herramientas; en particular, un test que verifique que un tenant no puede leer datos de otro.
- Si una decisión mía te parece equivocada, dímelo antes de implementarla.

**Empieza por la Fase 1.** Primero muéstrame el esquema de base de datos que propones (con tus correcciones al listado de arriba) y espera mi aprobación antes de crear las migraciones.