# WhatsApp automático (WhatsApp Cloud API de Meta)

Con la Cloud API, SolarFix envía los mensajes **solo**, desde el número del negocio, y muestra en cada orden si el mensaje fue **entregado** o **leído** y lo que **respondió el cliente**. Sin conectarla, la app sigue como siempre: abre WhatsApp con el mensaje listo y una persona lo envía.

## Lo que necesitas

- Una cuenta de **Meta Business** (business.facebook.com) — gratis.
- Un **número de teléfono para el negocio** que **no** esté usándose en la app de WhatsApp (uno nuevo, o pasar el actual a la API; un número registrado en la API ya no se usa desde el celular).
- La app **publicada con HTTPS** (`docs/PUBLICACION.md`): los mensajes salen igual sin ella, pero los estados (entregado/leído) y las respuestas llegan por el webhook, que necesita una dirección pública.

**Costo** (Meta, referencia 2026): los avisos que inicia el negocio (plantillas de "Utilidad") cuestan alrededor de **0,01–0,03 USD** cada uno en Ecuador. Responder a un cliente que escribió en las últimas 24 horas no se cobra. Meta factura a la tarjeta registrada en la cuenta.

## Paso 1 — Crear la app en Meta

1. Entra a **developers.facebook.com** → *Mis apps* → **Crear app** → tipo **Empresa** → asóciala a tu cuenta de Meta Business.
2. En la app, agrega el producto **WhatsApp** → *Configuración de la API*.
3. Meta te da un **número de prueba** (sirve para enviar a hasta 5 números que registres como destinatarios). Para usar el tuyo: **Agregar número de teléfono** y verifícalo por SMS o llamada.
4. Anota el **Identificador del número de teléfono** (Phone number ID).

## Paso 2 — Token permanente

El token que aparece en la pantalla de la API dura 24 horas: sirve solo para probar. Para producción:

1. **business.facebook.com** → *Configuración del negocio* → *Usuarios* → **Usuarios del sistema** → *Agregar* (rol **Administrador**).
2. **Asignar activos** → tu app (control total) y tu cuenta de WhatsApp.
3. **Generar token** → elige la app, vencimiento **Nunca**, permisos `whatsapp_business_messaging` y `whatsapp_business_management`.
4. Copia el token (solo se muestra una vez).

## Paso 3 — Datos en el servidor

En el `.env` de SolarFix:

```dotenv
WHATSAPP_TOKEN=el-token-permanente-del-paso-2
WHATSAPP_PHONE_NUMBER_ID=el-identificador-del-numero
WHATSAPP_VERIFY_TOKEN=una-frase-que-inventas-tu
WHATSAPP_APP_SECRET=la-clave-secreta-de-la-app
```

La **clave secreta** está en *developers.facebook.com → tu app → Configuración de la app → Básica*. Después: `php artisan config:cache`.

## Paso 4 — Plantillas de mensaje

WhatsApp solo permite que el negocio **inicie** una conversación con plantillas aprobadas por Meta (texto fijo con variables). Créalas en **business.facebook.com → WhatsApp Manager → Plantillas de mensajes → Crear plantilla**, con estos datos exactos (la aprobación suele tardar minutos):

### Presupuesto

- **Nombre:** `solarfix_presupuesto`
- **Categoría:** Utilidad · **Idioma:** Español
- **Cuerpo:**

```
Hola {{1}}, su equipo {{2}} (orden {{3}}) ya fue diagnosticado. Total del presupuesto: {{4}}. Puede ver el detalle y aprobarlo o rechazarlo aquí: {{5}}
```

- **Ejemplos de las variables** (Meta los pide para revisar la plantilla):

| Variable | Qué es | Ejemplo |
|---|---|---|
| `{{1}}` | cliente | Ana Pérez |
| `{{2}}` | equipo | Celular Samsung A15 |
| `{{3}}` | orden | ORD-MAT-20261014-0001 |
| `{{4}}` | total | $45.00 |
| `{{5}}` | enlace de seguimiento | https://taller.midominio.com/seguimiento/abc123 |

### Equipo listo

- **Nombre:** `solarfix_equipo_listo`
- **Categoría:** Utilidad · **Idioma:** Español
- **Cuerpo:**

```
Hola {{1}}, su equipo {{2}} (orden {{3}}) está listo para retirar. {{4}} Estado de su orden: {{5}}
```

- **Ejemplos de las variables** (Meta los pide para revisar la plantilla):

| Variable | Qué es | Ejemplo |
|---|---|---|
| `{{1}}` | cliente | Ana Pérez |
| `{{2}}` | equipo | Celular Samsung A15 |
| `{{3}}` | orden | ORD-MAT-20261014-0001 |
| `{{4}}` | saldo | Saldo pendiente: $45.00. |
| `{{5}}` | enlace de seguimiento | https://taller.midominio.com/seguimiento/abc123 |

### Entrega y garantía

- **Nombre:** `solarfix_entrega`
- **Categoría:** Utilidad · **Idioma:** Español
- **Cuerpo:**

```
Hola {{1}}, gracias por su confianza. Le entregamos su equipo {{2}} (orden {{3}}). {{4}} Califique nuestro servicio aquí: {{5}}
```

- **Ejemplos de las variables** (Meta los pide para revisar la plantilla):

| Variable | Qué es | Ejemplo |
|---|---|---|
| `{{1}}` | cliente | Ana Pérez |
| `{{2}}` | equipo | Celular Samsung A15 |
| `{{3}}` | orden | ORD-MAT-20261014-0001 |
| `{{4}}` | garantía | Garantía de 30 días, hasta el 13/11/2026. |
| `{{5}}` | enlace de seguimiento | https://taller.midominio.com/seguimiento/abc123 |

### Recordatorio de retiro

- **Nombre:** `solarfix_recordatorio_retiro`
- **Categoría:** Utilidad · **Idioma:** Español
- **Cuerpo:**

```
Hola {{1}}, le recordamos que su equipo {{2}} (orden {{3}}) está listo para retirar desde hace {{4}} días. Estado de su orden: {{5}}
```

- **Ejemplos de las variables** (Meta los pide para revisar la plantilla):

| Variable | Qué es | Ejemplo |
|---|---|---|
| `{{1}}` | cliente | Ana Pérez |
| `{{2}}` | equipo | Celular Samsung A15 |
| `{{3}}` | orden | ORD-MAT-20261014-0001 |
| `{{4}}` | días | 8 |
| `{{5}}` | enlace de seguimiento | https://taller.midominio.com/seguimiento/abc123 |

### Confirmación de cita

- **Nombre:** `solarfix_cita_confirmada`
- **Categoría:** Utilidad · **Idioma:** Español
- **Cuerpo:**

```
Hola {{1}}, su cita de {{2}} quedó agendada para el {{3}}. {{4}} Si necesita reprogramar, responda a este mensaje.
```

- **Ejemplos de las variables** (Meta los pide para revisar la plantilla):

| Variable | Qué es | Ejemplo |
|---|---|---|
| `{{1}}` | cliente | Ana Pérez |
| `{{2}}` | servicio | visita técnica |
| `{{3}}` | fecha y hora | jueves 16 de octubre a las 10:00 |
| `{{4}}` | dirección | Dirección: Av. Amazonas 123. |

### Recordatorio de cita

- **Nombre:** `solarfix_recordatorio_cita`
- **Categoría:** Utilidad · **Idioma:** Español
- **Cuerpo:**

```
Hola {{1}}, le recordamos su cita de {{2}} para mañana {{3}}. {{4}} Si necesita reprogramar, responda a este mensaje.
```

- **Ejemplos de las variables** (Meta los pide para revisar la plantilla):

| Variable | Qué es | Ejemplo |
|---|---|---|
| `{{1}}` | cliente | Ana Pérez |
| `{{2}}` | servicio | visita técnica |
| `{{3}}` | hora | a las 10:00 |
| `{{4}}` | dirección | Dirección: Av. Amazonas 123. |

### Mantenimiento preventivo

- **Nombre:** `solarfix_mantenimiento`
- **Categoría:** Utilidad · **Idioma:** Español
- **Cuerpo:**

```
Hola {{1}}, según su plan de mantenimiento, {{2}} el mantenimiento de {{3}}. ¿Desea que agendemos una visita? Responda a este mensaje.
```

- **Ejemplos de las variables** (Meta los pide para revisar la plantilla):

| Variable | Qué es | Ejemplo |
|---|---|---|
| `{{1}}` | cliente | Ana Pérez |
| `{{2}}` | cuándo | el 20 de octubre le corresponde |
| `{{3}}` | equipo | Aire acondicionado de la sala |

> Si Meta te pide otro nombre o usas otro idioma (`es_EC`, `es_MX`…), escríbelo en **Configuración → Envío de WhatsApp → Nombres de las plantillas / Idioma**.
>
> Los textos de **Configuración → Textos de WhatsApp** se usan solo en el modo enlace. Con la API, el cliente recibe el texto aprobado en Meta.

## Paso 5 — Webhook (estados y respuestas)

1. *developers.facebook.com → tu app → WhatsApp → Configuración* → **Webhook → Editar**.
2. **URL de devolución de llamada:** `https://TU-DOMINIO/webhooks/whatsapp` (aparece en Configuración → Envío de WhatsApp).
3. **Token de verificación:** el mismo `WHATSAPP_VERIFY_TOKEN` del `.env` → *Verificar y guardar*.
4. En **Campos del webhook**, suscríbete a **`messages`**.

## Paso 6 — Activar en SolarFix

1. **Configuración → Envío de WhatsApp → Probar la conexión:** escribe tu número y *Enviar prueba*. Debe llegarte "Hello World" (con el número de prueba de Meta, tu número tiene que estar entre los destinatarios permitidos).
2. **Modo de envío:** *WhatsApp Cloud API*.
3. Marca **Enviar solos los avisos** si quieres el envío automático:
   - **Al cambiar la orden:** presupuesto (al pasar a "Esperando aprobación"), equipo listo y entrega.
   - **Cada tarde (17:00):** recordatorio de las citas de mañana y de los equipos listos que llevan varios días sin retirarse (Configuración → Alertas → *Recordar retiro después de*). Requiere el cron (`docs/PUBLICACION.md`).
4. Guarda. Los botones de WhatsApp de órdenes, citas y mantenimientos ahora envían directo.

## Cómo se ve en la app

- En cada orden, la tarjeta **WhatsApp** muestra los mensajes con su estado: ✓ Enviado · ✓✓ Entregado · ✓✓ Leído (azul) · ✗ Falló (al pasar el mouse, el motivo).
- Cuando el cliente responde, su mensaje aparece en la orden como **Respuesta del cliente** y llega un aviso (campana) al técnico de la orden y a recepción de la sucursal. Para contestar, usa WhatsApp Business o el número desde Meta Business Suite; si respondes dentro de las 24 horas no hay costo.
- **Estado del sistema** avisa si el modo API está activo sin credenciales o sin HTTPS.

## Problemas comunes

| Mensaje | Qué hacer |
|---|---|
| *Template name does not exist* / *template not found* | La plantilla no está aprobada o el nombre/idioma no coincide con Configuración. |
| *Recipient phone number not in allowed list* | Estás con el número de prueba de Meta: agrega el destinatario en *Configuración de la API → Para*. |
| *Invalid OAuth access token* | El token venció (era el temporal) o se copió mal: genera el permanente (paso 2). |
| *Number of parameters does not match* | La plantilla en Meta no tiene las mismas variables `{{1}}…` que esta guía. |
| No llegan estados ni respuestas | Revisa el paso 5, que la app tenga HTTPS y que `WHATSAPP_APP_SECRET` sea el correcto (si no coincide, los avisos se rechazan). |
