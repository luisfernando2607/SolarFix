# Guía de pruebas manuales — SolarFix

Lista para probar la app en el navegador antes de usarla con clientes reales. Cada paso dice qué hacer y qué debe pasar (**→**). Marca `[x]` lo que funcione y anota lo que no.

Las pruebas automáticas (`php artisan test`) ya cubren la lógica; esta guía revisa lo que solo se ve usando la app: pantallas, botones, el celular, el PDF y WhatsApp.

## 0. Preparación

- [ ] `npm run build` y `php artisan migrate --seed` en una base de **prueba** (no la real).
- [ ] Entra como `admin@solarfix.ec` / `password`.
- [ ] **Usuarios → Nuevo**: crea un **técnico** (comisión: porcentaje, 10) y una **recepcionista**. Usa un navegador en modo incógnito para entrar con cada rol.
- [ ] **Configuración**: escribe el nombre, teléfono y dirección del negocio; revisa el IVA (15%, precios con IVA incluido).
- [ ] Ten a mano un **celular** (o la vista móvil del navegador, F12 → icono de celular).

## 1. Menú y roles

- [ ] En el celular: el botón ☰ abre el menú; tocar fuera o elegir una opción lo cierra.
- [ ] Como **técnico**: no ve Facturas, Caja, Reportes, Auditoría ni Usuarios. → Si abre esas URLs a mano, sale "403".
- [ ] Como **recepcionista**: ve Clientes, Facturas, Caja, Garantías, Agenda y Mantenimientos; no ve Usuarios ni Reportes.
- [ ] Desactiva al técnico (Usuarios → editar → desmarcar "Activo") e intenta entrar con él. → No puede. Vuelve a activarlo.

## 2. Recepción de un equipo (recepcionista)

- [ ] **Órdenes → Nueva**: busca un cliente por **cédula** y por **teléfono**. → Aparece en la lista.
- [ ] Crea otra orden con un cliente **nuevo**; deja el teléfono vacío. → Pide el teléfono.
- [ ] Elige celular, marca, modelo, PIN `1234`, accesorios, una foto, técnico asignado y un anticipo de $10.
- [ ] Al guardar → la orden muestra el anticipo como pago y el saldo correcto.
- [ ] **Checklists → Recepción**: marca un ítem "Con daño" con nota y el resto con "Marcar pendientes como OK".
- [ ] **Firmas → Firmar recepción**: el resumen muestra el daño marcado; firma con el dedo. → La firma aparece en la orden.
- [ ] Abre la foto de la orden en una ventana de incógnito **sin sesión**. → No se ve (redirige al login).

## 3. Diagnóstico y presupuesto (técnico)

- [ ] Como técnico, la campana del menú muestra "1" por la orden asignada; al abrir la notificación lleva a la orden.
- [ ] Como técnico, en **Órdenes** solo ve las suyas y las sin asignar.
- [ ] Edita la orden: estado "En diagnóstico", diagnóstico escrito, mano de obra $40.
- [ ] **Repuestos → Agregar**: busca un repuesto del inventario (créalo antes como admin si no hay). → Se descuenta el stock y el total sube.
- [ ] Intenta agregar más unidades de las que hay. → "Stock insuficiente".
- [ ] **WhatsApp → Presupuesto**: se abre WhatsApp con diagnóstico y montos (un renglón por repuesto). → La orden pasa a "Esperando aprobación".

## 4. Reparación, control de calidad y entrega

- [ ] Estado "En reparación" y luego "Listo" sin completar el checklist de control de calidad. → Aparece el aviso "falta el control de calidad".
- [ ] Completa el control de calidad. → El aviso desaparece.
- [ ] **WhatsApp → Equipo listo**: mensaje con el saldo pendiente.
- [ ] Como recepcionista, en **Pagos** de la orden intenta cobrar más que el saldo. → Lo rechaza.
- [ ] Cobra el saldo exacto. → Saldo $0.00; el comprobante se imprime. La recepcionista no ve el botón de eliminar pagos.
- [ ] **Firmar entrega**: firma. → La orden queda "Entregado", con garantía emitida (30 días) y comisión para el técnico.
- [ ] **PDF**: revisa que tenga costos, repuestos, checklist de recepción con el daño, garantía y las dos firmas.
- [ ] **WhatsApp → Entrega y garantía**: mensaje con la fecha de vencimiento.

## 5. Cierre sin reparación

- [ ] Nueva orden con costo de diagnóstico $10; cámbiala a "Cerrado sin reparación".
- [ ] **Firmar devolución**: la pantalla avisa que falta cobrar el diagnóstico. Firma. → La orden sigue "Cerrado sin reparación", sin garantía ni comisión.

## 6. Facturas

- [ ] En la orden entregada: **Facturar**. → La factura muestra base + IVA y el total igual al de la orden; un renglón por repuesto.
- [ ] Vuelve a pulsar Facturar. → Lleva a la factura existente (no crea otra).
- [ ] Como admin, **Anular** la factura y vuelve a facturar. → Se crea una nueva con los pagos asociados.

## 7. Garantías

- [ ] **Garantías**: aparece la de la orden entregada.
- [ ] En la orden: **Abrir reclamo de garantía**. → Se crea una orden "En garantía" vinculada, sin costo, con el mismo técnico.
- [ ] Agrega un repuesto a la orden de garantía. → Descuenta stock pero el cliente paga $0.

## 8. Inventario (admin y técnico)

- [ ] Crea un repuesto con stock inicial 2 y mínimo 3. → Aparece en "Stock bajo" y en el dashboard.
- [ ] Como admin, crea un **Proveedor**.
- [ ] Como técnico, **Registrar entrada** de 10 unidades eligiendo el proveedor. → Sale de stock bajo; el costo se promedia; la compra aparece en la ficha del proveedor.
- [ ] Como admin, **Ajuste por conteo** sin motivo. → Lo pide. Con motivo → queda en movimientos.

## 9. Caja del día (recepcionista)

- [ ] **Caja**: registra fondo inicial $50, un gasto $5 y un retiro $20.
- [ ] Cuenta el efectivo y escribe un monto distinto al esperado sin observaciones. → Pide la justificación.
- [ ] Cierra con el monto correcto. → "Diferencia $0.00" en el historial.

## 10. Agenda y mantenimientos (recepcionista)

- [ ] **Agenda → Nueva cita** para mañana con el técnico. → Aparece en la semana; la dirección se completa con la del cliente.
- [ ] Crea otra cita del mismo técnico a la misma hora. → Avisa del choque de horario.
- [ ] En la cita: **WhatsApp: confirmación** → la cita pasa a "Confirmada".
- [ ] En la Agenda, panel "Recordatorios por enviar": **Enviar recordatorio** → desaparece del panel.
- [ ] En la cita: **Crear orden de servicio** → el formulario llega con el cliente cargado y la orden queda vinculada.
- [ ] **Mantenimientos → Nuevo plan** cada 6 meses con fecha dentro de 5 días. → Aparece en la lista y en el dashboard.
- [ ] **Agendar** desde el plan (asígnale el técnico), y como técnico marca la cita **completada**. → El plan pasa a la fecha de la cita + 6 meses.
- [ ] Como técnico, la agenda solo muestra sus citas.

## 11. Cliente, comisiones, reportes y auditoría (admin)

- [ ] **Clientes → ver**: muestra sus órdenes, total pagado, saldo, garantías, citas y planes.
- [ ] **Comisiones**: la del técnico aparece pendiente → **Pagar**. Como técnico, solo ve las suyas.
- [ ] **Reportes**: el gráfico de 12 meses muestra la barra del mes; pasa el mouse sobre ella para ver el monto.
- [ ] **Excel: órdenes / pagos**: el archivo abre en Excel en columnas, con tildes y decimales con coma.
- [ ] **Auditoría**: aparecen los cambios de costos, el pago de comisiones y un intento de acceso fallido (pruébalo con una contraseña errada).

## Al terminar

Anota cualquier pantalla que se vea mal, botón que no haga lo esperado o mensaje confuso, con el rol que usabas y los pasos para repetirlo.
