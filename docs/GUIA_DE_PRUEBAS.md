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

- [ ] El dashboard muestra **Mostrador**: listos para entregar, esperando aprobación y recibidos hoy.
- [ ] En la barra superior (o con la tecla `/`) busca por teléfono, cédula, IMEI o número de orden. → Un número de orden exacto abre la orden directamente.
- [ ] En la orden, **Ticket**: se abre la impresión de 80 mm con el QR de seguimiento. Escanéalo con el celular. → Abre la página de seguimiento.

- [ ] **Órdenes → Nueva**: busca un cliente por **cédula** y por **teléfono**. → Aparece en la lista.
- [ ] Crea otra orden con un cliente **nuevo**; deja el teléfono vacío. → Pide el teléfono.
- [ ] Elige celular, marca, modelo, PIN `1234`, accesorios, una foto, técnico asignado y un anticipo de $10.
- [ ] Al guardar → la orden muestra el anticipo como pago y el saldo correcto.
- [ ] **Checklists → Recepción**: marca un ítem "Con daño" con nota y el resto con "Marcar pendientes como OK".
- [ ] **Firmas → Firmar recepción**: el resumen muestra el daño marcado; firma con el dedo. → La firma aparece en la orden.
- [ ] Abre la foto de la orden en una ventana de incógnito **sin sesión**. → No se ve (redirige al login).

## 3. Diagnóstico y presupuesto (técnico, desde el celular)

- [ ] El dashboard del técnico muestra **Mi trabajo** por etapa, con los días en taller, y las órdenes sin técnico con el botón **Tomar**.
- [ ] Toma una orden sin técnico. → Pasa a ser suya.
- [ ] En la orden, **Acciones rápidas → Iniciar diagnóstico** con una nota. → El estado cambia y la nota aparece en el historial.
- [ ] **Trabajo técnico**: escribe el diagnóstico y la mano de obra → Guardar. → El total de la orden se actualiza.
- [ ] **Bitácora**: agrega una nota de avance. → Aparece con tu nombre y la hora.
- [ ] **Fotos**: en el celular, "Tomar o elegir foto" abre la cámara; súbela como "Proceso".

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
- [ ] **Firmar entrega** con saldo pendiente: la pantalla permite **cobrar en el mismo paso** (monto, método, referencia). Firma. → Pago registrado, saldo $0, orden "Entregado", garantía emitida y comisión para el técnico.
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
- [ ] Dashboard del admin: **Indicadores del mes** con la variación frente al mes anterior y **Cuentas por cobrar** (entrega una orden sin cobrar todo para verla).
- [ ] Crea dos clientes con el mismo teléfono escrito distinto (0991234567 y +593 99 123 4567). → **Clientes → Ver duplicados** los agrupa; únelos y revisa que las órdenes pasen al que conservaste.
- [ ] **Comisiones**: la del técnico aparece pendiente → **Pagar**. Como técnico, solo ve las suyas.
- [ ] **Reportes**: el gráfico de 12 meses muestra la barra del mes; pasa el mouse sobre ella para ver el monto.
- [ ] **Excel: órdenes / pagos**: el archivo abre en Excel en columnas, con tildes y decimales con coma.
- [ ] **Auditoría**: aparecen los cambios de costos, el pago de comisiones y un intento de acceso fallido (pruébalo con una contraseña errada).

## 12. Multi-sucursal (admin)

- [ ] **Sucursales → Nueva**: crea "Quito" con código QTO. → Aparece el selector de sucursal en el menú.
- [ ] Crea una recepcionista y un técnico de Quito. Como recepcionista de Quito, recibe un equipo. → El número es ORD-QTO-…; no ve las órdenes de la otra sucursal.
- [ ] Como admin, cambia el selector entre "Todas" y cada sucursal. → Órdenes, caja, reportes e indicadores se filtran.
- [ ] **Inventario**: en un repuesto, registra entrada en una sucursal y **traslada** unidades a la otra. → El stock de cada sucursal cambia y el total no.
- [ ] Agrega ese repuesto a una orden de Quito. → Se descuenta del stock de Quito.
- [ ] **Caja**: cada sucursal tiene su propio cuadre del día.

## 13. Seguridad

- [ ] Crea un usuario nuevo e inicia sesión con él en modo incógnito. → Antes de continuar, la app pide cambiar la contraseña.
- [ ] Con dos navegadores abiertos con el mismo usuario, cambia la contraseña en uno (menú → **Cambiar contraseña**). → El otro se cierra al recargar.
- [ ] Como **admin** (no super admin): en **Usuarios**, los super admin no tienen botón de editar ni eliminar, y "Super Admin" no aparece como rol.
- [ ] Con un usuario abierto en otro navegador, desactívalo como admin. → Al recargar, queda fuera.
- [ ] Como **técnico**, en "Editar orden" de una orden recibida intenta pasarla directo a "Listo". → No lo permite; solo el siguiente paso.
- [ ] En "Editar orden" (cualquier rol) elige "Entregado". → Avisa que la entrega se hace desde **Entregar** (cobro y firma).
- [ ] Como **técnico**, al crear una orden el anticipo aparece bloqueado en 0.
- [ ] Como **recepcionista de Guayaquil**, cambia en la URL el número de una orden de Quito para cobrarla o facturarla. → "403".
- [ ] Deja un campo obligatorio vacío en cualquier formulario. → El mensaje sale en español.

## 14. Dinero que cuadra

- [ ] Registra un anticipo de $100 y luego baja la mano de obra a $60 en "Editar". → Aparece "Saldo a favor del cliente $40" y el formulario de **devolución**; al registrarla, sale de la caja del día.
- [ ] Factura una orden con saldo. → La factura queda "Emitida"; al cobrar el saldo pasa sola a "Pagada".
- [ ] Con la factura vigente intenta cambiar la mano de obra o agregar un repuesto. → Lo impide y pide anular la factura.
- [ ] En **Configuración** desmarca "precios con IVA incluido" y factura una orden de $100. → El total de la orden pasa a $115 y el saldo pide cobrar el IVA. Anula la factura. → La orden vuelve a $100.
- [ ] Intenta eliminar una orden con pagos o con factura vigente. → Lo impide.
- [ ] Cierra la caja de un día y al día siguiente intenta registrar un pago o un gasto con la fecha del día cerrado. → Lo impide.
- [ ] En una orden entregada, en "Editar" cambia el estado. → Avisa que debe registrarse un reclamo de garantía.

## 15. Funciones para el cliente y el mostrador

- [ ] Pasa una orden a "Esperando aprobación" y abre su enlace de seguimiento en el celular. → Aparece el presupuesto con **Aprobar reparación**. Apruébalo. → La orden pasa a "En reparación" y el técnico recibe un aviso.
- [ ] En otra orden, elige "No deseo la reparación" y escribe un motivo. → La orden se cierra sin reparación y el motivo queda en el historial.
- [ ] Abre el enlace de una orden entregada. → Pide calificar con estrellas; con 3 o menos llega un aviso a la administración. La calificación se ve en la orden y en **Reportes**.
- [ ] **Ventas → Nueva venta**: busca un accesorio, agrega 2 unidades y cobra. → Baja el stock, aparece en **Caja del día** y se imprime el comprobante.
- [ ] Como admin, anula la venta. → El stock vuelve y la caja ya no la suma.
- [ ] Abre `/agendar` sin iniciar sesión y pide una visita. → Recepción recibe un aviso y la cita aparece "Solicitada en línea" en la agenda.
- [ ] En una orden, **Etiqueta**. → Se abre la etiqueta de 50 × 30 mm lista para imprimir; el QR abre la orden.
- [ ] En **Configuración → Página del cliente** desactiva cada opción. → Desaparece de la página pública.

## 16. Gestión del negocio (admin)

- [ ] **Reportes → Utilidad estimada**: con una orden entregada que usó repuestos, una venta y un gasto de caja, los montos coinciden con lo registrado.
- [ ] **Inventario → Compras sugeridas**: un repuesto que se vende seguido y quedó bajo aparece con su proveedor, cuánto pedir y el costo; el Excel abre en columnas.
- [ ] **Ventas → Nueva venta** con un lector de código de barras: al escanear, el producto se agrega solo.
- [ ] En el **Dashboard** de recepción aparecen las citas pedidas en línea por confirmar y los saldos a favor por devolver.

## 17. Operación diaria

- [ ] **Importar datos**: descarga la plantilla de clientes, agrega 3 filas (una repetida y una sin teléfono) e impórtala. → Dice cuántos se crearon, cuántos ya existían y qué filas tienen error.
- [ ] Importa la plantilla de repuestos con stock. → Aparecen en Inventario con su stock en la sucursal y un ajuste "Carga inicial desde archivo".
- [ ] Sube una foto grande desde el celular a una orden. → Se ve bien orientada y carga rápido.
- [ ] **Reportes → Por técnico**: muestra días promedio, calificación y reclamos de garantía de cada técnico.
- [ ] Una orden lista hace más de 7 días (configurable) muestra **Recordar retiro** en el Mostrador; el mensaje dice cuántos días lleva lista.

## 18. Logo y verificación en dos pasos

- [ ] **Configuración → Negocio**: sube el logo. → Aparece en el ticket, la factura, el comprobante, el PDF de la orden y la página de seguimiento.
- [ ] Menú → **Verificación en dos pasos** → escanea el QR con Google Authenticator y escribe el código. → Muestra 8 códigos de respaldo; guárdalos.
- [ ] Cierra sesión y vuelve a entrar. → Después de la contraseña pide el código del celular.
- [ ] Entra usando un código de respaldo. → Funciona una sola vez.
- [ ] **Configuración → Seguridad**: exige la verificación a los administradores. → Un admin sin ella debe configurarla antes de usar la app.

## 19. Presupuesto y compras

- [ ] En una orden con costos, **Presupuesto**. → PDF con logo, diagnóstico, conceptos, validez y QR para aprobar.
- [ ] Abre el enlace de seguimiento de una orden esperando aprobación. → "Descargar presupuesto (PDF)".
- [ ] **Inventario → Compras sugeridas → Crear orden de compra** en un proveedor. → Borrador con lo sugerido; cambia cantidades, agrega un repuesto y guarda.
- [ ] **Marcar como enviada** y **WhatsApp al proveedor**. → Abre WhatsApp con la lista del pedido.
- [ ] **Registrar recepción** con una cantidad menor a la pedida y otro costo. → El stock sube lo recibido y el costo promedio cambia.

## Al terminar

Anota cualquier pantalla que se vea mal, botón que no haga lo esperado o mensaje confuso, con el rol que usabas y los pasos para repetirlo.
