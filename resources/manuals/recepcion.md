# Manual de Recepción

Recepción recibe los equipos, atiende a los clientes, cobra, entrega, factura y cuadra la caja. Todo se hace desde el menú de la izquierda (en el celular, con el botón ☰).

## 1. Empezar el día

1. Entra con tu correo y contraseña. La primera vez la app te pide **cambiar la contraseña** que te dio el administrador.
2. En el **Dashboard** revisa el panel **Mostrador**:
   - **Listos para entregar**: equipos terminados. Los que llevan muchos días aparecen en rojo con el botón **Recordar retiro**.
   - **Esperando aprobación**: presupuestos sin respuesta del cliente (**Reenviar presupuesto**).
   - **Recibidos hoy**.
3. Más abajo: citas de hoy, **citas pedidas en línea por confirmar** y **saldos a favor por devolver**.
4. En **Caja del día**, registra el **Fondo inicial** (el efectivo con el que abres).

![Panel Mostrador: equipos listos para entregar, con los botones Recordar retiro y Entregar](/manuales/r-mostrador.jpg)


> Atajo: la tecla **/** abre el buscador. Busca por número de orden, nombre, teléfono, cédula o IMEI.

## 2. Recibir un equipo

1. **Órdenes → Nueva Orden** (o **+ Recibir equipo** en el Mostrador).
2. **Cliente**: escribe nombre o cédula en *Buscar cliente*. Si no existe, llena *Nombre*, *Cédula* y *Teléfono* (el teléfono es obligatorio: por ahí le llegan los mensajes).
3. **Equipo**: *Tipo* (elígelo de la lista), *Marca*, *Modelo*, *Serie/IMEI* y el estado físico (rayones, golpes).
4. **Falla declarada**: lo que dice el cliente, con sus palabras.
5. **Desbloqueo**: PIN, patrón o contraseña si el técnico lo necesitará (se guarda cifrado).
6. **Accesorios** que deja (cargador, funda…) y **fotos** del equipo al recibirlo.
7. **Costos**: el costo de diagnóstico y, si el cliente deja dinero, el **Anticipo**.
8. **Técnico asignado** (o déjalo sin asignar: los técnicos pueden tomarlo).
9. **Crear Orden**.

![Formulario de nueva orden en el celular](/manuales/r-nueva-orden.jpg)


Después, en la orden:
- **Ticket**: imprímelo (impresora de 80 mm) y entrégalo al cliente. Tiene el QR de seguimiento.
- **Etiqueta**: pégala en el equipo para identificarlo en el taller.
- **Checklists → Recepción → Completar**: revisa el equipo con el cliente presente y marca lo dañado.
- **Firmas → Firmar recepción**: el cliente firma en la pantalla.

![Orden con los botones PDF, Presupuesto, Ticket, Etiqueta y Editar, y las acciones rápidas](/manuales/r-orden.jpg)


## 3. Mientras el equipo está en el taller

- El cliente puede ver el estado con el **enlace de seguimiento** (QR del ticket o *Seguimiento del cliente → Copiar*). Así no tiene que llamar.
- Cuando el técnico termina el diagnóstico, envía el **presupuesto** por WhatsApp (botón **Presupuesto** en la tarjeta *WhatsApp* de la orden). El cliente puede **aprobarlo o rechazarlo desde su enlace**; si te responde por teléfono, el técnico o tú registran la respuesta con las *Acciones rápidas*.
- **Presupuesto** (botón arriba en la orden) genera el PDF para imprimir o enviar.
- Si el cliente quiere pagar algo antes, **Pagos → Registrar Pago**.

## 4. Entregar y cobrar

1. Abre la orden (en **Listos para entregar**) y presiona **Cobrar $… y entregar**.
2. Muestra al cliente el resumen (trabajo, total, garantía).
3. En **Cobrar el saldo antes de entregar**: monto, método (efectivo, transferencia, tarjeta…) y referencia si aplica. Si no paga ahora, deja 0: el saldo queda pendiente.
4. El cliente **firma** en el recuadro → **Firmar y marcar como entregado**.

![Entrega: firma del cliente y cobro del saldo](/manuales/r-entrega.jpg)

5. La app emite la **garantía** y, si está activado, le envía al cliente el aviso de entrega con el enlace para **calificar** el servicio.

Si el cliente no quiso la reparación, la orden queda "Cerrado sin reparación": se entrega igual con su firma (devolución sin reparar).

**Saldo a favor**: si se cobró de más (por ejemplo, bajó el costo después de un anticipo), la orden muestra *Saldo a favor del cliente* y el formulario **Registrar devolución**.

## 5. Facturar

- Desde la orden: **Facturar** (en *Costos*). La factura toma los datos de la orden y se marca **Pagada** sola cuando la orden queda saldada.
- Factura sin orden: **Facturas → Nueva Factura**, busca el cliente o escribe sus datos y agrega los ítems.
- Con la factura vigente no se pueden cambiar los costos de la orden; si hay un error, pide al administrador **anularla**.

## 6. Ventas de mostrador

Para accesorios y repuestos que se venden sin reparar nada:

1. **Ventas → Nueva venta**.
2. Busca el producto por nombre o código, o **escanéalo con el lector de código de barras** (se agrega solo).
3. Ajusta las cantidades, elige el cliente si quieres (opcional) y el método de pago.
4. **Cobrar y registrar venta** → **Imprimir comprobante**.

![Nueva venta de mostrador con dos productos](/manuales/r-venta.jpg)


El stock baja solo y la venta entra a la caja del día.

## 7. Citas y mantenimientos

- **Agenda → Nueva cita**: cliente, tipo (visita técnica, instalación…), fecha, hora, dirección y técnico. La app avisa si el técnico ya tiene otra cita a esa hora.
- Envía la **confirmación** y, el día anterior, el **recordatorio** por WhatsApp desde la cita (o salen solos si el administrador activó el envío automático).
- Las **citas pedidas en línea** (página *Agendar* que el negocio comparte en redes) aparecen en el Dashboard como "por confirmar": ábrelas, asigna técnico y confírmalas.
- **Mantenimientos**: planes de mantenimiento preventivo por cliente. Cuando se acerca la fecha, aparecen en el Dashboard; avisa al cliente y agenda la visita.

## 8. Garantías

Si un cliente vuelve con un equipo en garantía, busca la orden original (por teléfono o número de orden) y presiona **Abrir reclamo de garantía** (en la tarjeta *Garantía*). Se crea una orden nueva vinculada; lo que cubre la garantía no se cobra. En **Garantías** ves todas las vigentes y las que están por vencer.

## 9. Cerrar la caja

1. **Caja del día**: revisa los **cobros y ventas** del día por método de pago.
2. Registra **gastos** (taxi, compras menores) y **retiros** (depósito al banco) en *Movimientos de efectivo*.
3. Cuenta el efectivo y escribe el **Efectivo contado**. Si no coincide con el esperado, escribe la explicación.
4. **Cerrar caja**. Si después del cierre entra otro cobro del mismo día, vuelve a cerrar.

![Caja del día: cobros y ventas por método de pago](/manuales/r-caja.jpg)


Un día anterior ya cerrado no se puede modificar.

## Preguntas frecuentes

- **No encuentro un cliente**: busca por teléfono o cédula; si aparece repetido, avisa al administrador para **unir clientes duplicados**.
- **El cliente perdió su ticket**: búscalo por teléfono y vuelve a imprimir el **Ticket**.
- **Me equivoqué en un pago**: solo el administrador puede eliminar pagos.
- **No veo una orden**: cada sucursal ve solo sus órdenes.
