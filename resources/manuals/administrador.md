# Manual del Administrador

El administrador ve **todas las sucursales** (con el selector *Sucursal* del menú puede filtrar por una), configura el negocio y controla el dinero, el inventario y al personal. También puede hacer todo lo de Recepción y del Técnico (ver sus manuales).

## 1. Primeros pasos (una sola vez)

1. **Cambia tu contraseña** al primer ingreso y activa la **Verificación en dos pasos** (menú, abajo): escanea el QR con Google Authenticator y guarda los 8 códigos de respaldo.
2. **Configuración**:
   - **Negocio**: nombre, **RUC**, teléfono, correo, dirección y **logo** (salen en tickets, facturas, presupuestos y la página del cliente).
   - **Numeración y montos**: prefijos de orden y factura, moneda, IVA (incluido o no en los precios), costo de diagnóstico por defecto, días de garantía.
   - **Métodos de pago**, condiciones del ticket, validez del presupuesto.
   - **Página del cliente**: aprobación en línea, encuesta de satisfacción y citas en línea.
   - **Envío de WhatsApp** y **Correo a clientes** (ver *docs/WHATSAPP.md*).
   - **Seguridad**: exigir verificación en dos pasos a los administradores.
   - **Alertas**, **comisiones** y **limpieza automática**.
![Configuración → Negocio: nombre, RUC, teléfono, correo, dirección y logo](/manuales/a-negocio.jpg)

3. **Sucursales**: crea cada sucursal con su código (va en el número de orden: ORD-QTO-…), dirección y teléfono.
4. **Usuarios → Nuevo**: técnicos (con su comisión: fija o porcentaje), recepción y otros administradores. La contraseña que les das es temporal: la cambian al entrar.
5. **Catálogos**: Marcas, Modelos, Accesorios, **Checklists** por tipo de equipo (recepción, diagnóstico y control de calidad) y Proveedores.
6. **Importar datos**: carga clientes y repuestos desde Excel (descarga la plantilla, llénala y guárdala como CSV).
7. **Estado del sistema**: todo debe quedar en "Listo" (respaldos, HTTPS, contraseñas, datos del negocio).

## 2. El día a día

- **Dashboard**: indicadores del mes (ingresos, entregas, ticket promedio, días de reparación, aprobación de presupuestos, retrabajos) comparados con el mes anterior, cuentas por cobrar, valor del inventario y las alertas (órdenes demoradas, presupuestos sin respuesta, stock bajo, garantías por vencer).
- La campana te avisa de **calificaciones bajas** (3 estrellas o menos) para llamar al cliente.

## 3. Dinero

- **Caja del día**: revisa el cierre de cada sucursal (el selector de sucursal está arriba de la caja).
- **Pagos**: solo el administrador puede **eliminar un pago** (no en días ya cerrados).
- **Facturas**: anular o eliminar. Al anular una factura con IVA sumado, la orden vuelve a su total sin IVA.
- **Ventas**: anular una venta devuelve el stock (no en días ya cerrados).
- **Órdenes**: eliminar una orden solo se puede si no tiene pagos ni factura vigente.

## 4. Inventario y compras

- **Inventario → Nuevo repuesto**: código (puede ser el código de barras), categoría, costo, precio de venta y **stock mínimo**.
- **Ajustar** el stock después de un conteo físico (queda el motivo).
- **Compras sugeridas**: lo que conviene pedir, por proveedor, según lo usado y vendido en los últimos 90 días.

![Compras sugeridas por proveedor](/manuales/a-compras-sugeridas.jpg)

- **Crear orden de compra** desde la sugerencia → ajusta cantidades y costos → **PDF** o **WhatsApp al proveedor** → **Marcar como enviada**.
- Cuando llega la mercadería: **Registrar recepción** con lo que llegó y el costo de la factura. El stock y el costo promedio se actualizan solos.

![Orden de compra en borrador y recepción de la mercadería](/manuales/a-orden-compra.jpg)


## 5. Comisiones

**Comisiones**: por mes y técnico. **Registrar adelanto** cuando le das dinero antes de tiempo. **Pagar** marca las comisiones pendientes como pagadas y descuenta los adelantos. Si una orden con comisión ya pagada se corrige, la diferencia queda como **ajuste** para el próximo pago.

## 6. Reportes

**Reportes** (elige el período):
- Ingresos (órdenes y ventas), órdenes recibidas y entregadas, ticket promedio, días de reparación, satisfacción.
- **Utilidad estimada**: ingresos menos repuestos, comisiones, costo de lo vendido y gastos.

![Reportes → Utilidad estimada del período](/manuales/a-utilidad.jpg)

- Por técnico (órdenes, días, calificación, garantías), por tipo de equipo y repuestos más usados.
- **Excel**: órdenes, pagos y ventas.

## 7. Clientes

- **Clientes**: historial completo de cada cliente (órdenes, compras, garantías, citas y mantenimientos).
- **Clientes → Ver duplicados**: la app detecta clientes repetidos por teléfono o cédula y permite **unirlos** con todo su historial.

## 8. Control y seguridad

- **Auditoría**: quién cambió qué y cuándo (costos, pagos, usuarios, ingresos al sistema e intentos fallidos).
- Un usuario **desactivado** queda fuera al instante.
- Solo un super administrador puede crear o modificar a otro super administrador.

## 9. Respaldos

- Se hacen solos cada noche (si el servidor tiene el cron). Revisa **Estado del sistema**.
- Guarda una copia fuera del servidor y **prueba una restauración** de vez en cuando (`php artisan solarfix:restore`, ver README).
