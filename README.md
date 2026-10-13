<div align="center">

# 🔧 SolarFix

**Sistema de gestión integral para talleres de reparación**

Celulares · Tablets · PCs · Aires acondicionados · Televisores · Electrodomésticos

![Laravel](https://img.shields.io/badge/Laravel_13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP_8.3-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Tailwind](https://img.shields.io/badge/Tailwind_4-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)

<img src="docs/WhatsApp%20Image%202026-05-29%20at%202.52.04%20PM.jpeg" alt="Prototipo de diseño de la orden de servicio" width="90%"/>

<sub>Prototipo de diseño de la orden de servicio.</sub>

</div>

---

## 🛠️ El problema

Los talleres suelen registrar equipos en cuadernos o chats de WhatsApp: se pierden accesorios, nadie sabe en qué estado está una reparación y el cobro de saldos es un caos.

**SolarFix** centraliza la recepción, el diagnóstico, la reparación, la entrega y la posventa en un solo flujo trazable. Implementa los flujos de [`docs/SolarFix_Documento_De_Procesos.md`](docs/SolarFix_Documento_De_Procesos.md).

---

## ✨ Funcionalidades

| Módulo | Qué hace |
|---|---|
| 📋 **Órdenes de servicio** | Cliente, equipo (marca/modelo/IMEI), accesorios, fotos, PIN/patrón cifrado, falla declarada y técnico asignado |
| 🔄 **Estados** | Recibido → En diagnóstico → Esperando aprobación → En reparación → Listo → Entregado · Cerrado sin reparación · En garantía |
| ✅ **Checklists** | Recepción, diagnóstico y control de calidad por tipo de equipo; los daños se muestran al cliente antes de firmar |
| ✍️ **Firma digital** | El cliente firma en pantalla al recibir y al entregar (o al retirar sin reparar) |
| 💬 **WhatsApp** | Presupuesto, equipo listo, entrega con garantía, citas y mantenimientos (enlaces wa.me) |
| 📦 **Inventario** | Repuestos con stock, costo promedio, entradas por proveedor, ajustes y alerta de stock bajo; se descuentan al usarlos en una orden. **Compras sugeridas** por proveedor según el consumo de los últimos 90 días (Excel e impresión) |
| 💵 **Cobros y caja** | Pagos por orden, comprobantes, saldo a favor y devoluciones, caja del día con fondo inicial, gastos, retiros y cuadre; los días anteriores ya cerrados no se alteran |
| 🧾 **Facturas** | Desde la orden, con IVA configurable (incluido o no en los precios; si no, se suma al saldo de la orden). Pasa a "pagada" sola cuando la orden queda saldada y fija los costos mientras esté vigente |
| 🛡️ **Garantías** | Emitidas al entregar; el reclamo abre una orden vinculada y la cobertura (completa, mano de obra o repuestos) define qué se cobra |
| 📅 **Agenda** | Visitas a domicilio con choque de horarios, confirmación y recordatorio |
| 🔁 **Mantenimientos** | Planes preventivos por cliente que se renuevan al completar la visita |
| 💰 **Comisiones** | Fijas o porcentuales por técnico, generadas al entregar; adelantos que se descuentan al pagar |
| 📈 **Indicadores** | Mes actual vs. anterior (ingresos, entregas, ticket, días de reparación, aprobación de presupuestos, retrabajos), cuentas por cobrar y valor del inventario |
| 👥 **Clientes duplicados** | Detección por teléfono o cédula y unión con todo su historial |
| 📊 **Reportes** | Ingresos (órdenes y ventas), **utilidad estimada** (menos repuestos, comisiones, costo de lo vendido y gastos), órdenes, técnicos, tipos de equipo, repuestos y satisfacción; exportación a Excel de órdenes, pagos y ventas |
| 🏢 **Multi-sucursal** | Sucursales con código en el número de orden, caja e inventario por sucursal, traslados de repuestos y reportes por sucursal; los admins ven todas |
| ⚙️ **Configuración** | Datos del negocio y **logo** (en tickets, facturas, comprobantes y la página del cliente), IVA, prefijos de orden y factura, moneda, métodos de pago, textos de WhatsApp, alertas y comisiones |
| 🧑‍🔧 **Para técnicos** | Cola "Mi trabajo" por etapa, tomar órdenes sin técnico, acciones rápidas de estado, panel de diagnóstico y costos, bitácora y fotos con la cámara |
| 🧾 **Para recepción** | Búsqueda global (tecla `/`), panel Mostrador (listos, por aprobar, recibidos hoy), cobro y entrega en un paso, ticket de 80 mm con QR |
| 🔗 **Seguimiento del cliente** | Enlace privado y QR por orden: el cliente ve estado, fechas y saldo sin llamar al taller, **aprueba o rechaza el presupuesto en línea** y **califica el servicio** al recibir su equipo |
| 🛒 **Ventas de mostrador** | Accesorios y repuestos sin orden: descuenta stock de la sucursal, entra a la caja y a los reportes, comprobante de 80 mm y anulación |
| 🗓️ **Citas en línea** | Página pública `/agendar` para compartir en redes: el cliente pide una visita o turno y recepción la confirma |
| 📥 **Importar datos** | Clientes y repuestos desde Excel (CSV) con plantilla: omite clientes repetidos, actualiza repuestos por código y carga el stock inicial |
| 🏷️ **Etiqueta del equipo** | Adhesivo de 50 × 30 mm con número de orden, cliente y QR que abre la orden en la app |
| 📱 **App instalable** | Se agrega a la pantalla de inicio del celular y se abre como app (PWA) |
| 💾 **Respaldos** | Diarios y automáticos: base de datos, fotos y firmas en un .zip |
| 🔔 **Notificaciones** | Aviso al técnico cuando le asignan una orden, una cita o un reclamo |
| 🕵️ **Auditoría** | Quién cambió qué y cuándo (valor anterior y nuevo) e intentos de acceso |

### Roles

| Rol | Puede |
|---|---|
| `super_admin`, `admin` | Todas las sucursales (con selector para filtrar por una). Todo: usuarios, catálogos, configuración, reportes, auditoría, comisiones, precios y ajustes de inventario, eliminar órdenes, pagos y facturas. |
| `receptionist` | Solo su sucursal: clientes, órdenes, cobros, facturas, caja, garantías, agenda y mantenimientos. |
| `technician` | Solo su sucursal: sus órdenes y las sin asignar, repuestos y entradas de inventario, sus citas y sus comisiones. No ve facturas ni caja. |

Reglas de seguridad:

- Solo un `super_admin` crea, edita o elimina a otro `super_admin`, y siempre queda al menos uno activo. Nadie cambia su propio rol ni se desactiva a sí mismo.
- Un usuario desactivado queda fuera al instante, aunque tenga la sesión abierta.
- **Verificación en dos pasos** (Google/Microsoft Authenticator) para cualquier usuario, con 8 códigos de respaldo; se puede exigir a los administradores en Configuración.
- La contraseña que asigna otra persona (al crear o restablecer un usuario) se cambia en el primer ingreso. Cada usuario cambia la suya en **Cambiar contraseña**, y al hacerlo se cierran sus otras sesiones.
- La entrega se registra solo con cobro y firma, nunca desde "Editar orden". Los técnicos avanzan la orden paso a paso, no modifican órdenes cerradas, no registran cobros y en el buscador de clientes ven pocos resultados y sin correo ni dirección.

---

## 🧰 Stack

| Capa | Tecnología |
|---|---|
| Backend | Laravel 13 · PHP 8.3 |
| Frontend | Blade · Tailwind CSS 4 (Vite) · JavaScript sin frameworks · SweetAlert2 |
| PDF | DomPDF |
| Base de datos | MySQL (los tests usan SQLite en memoria) |

---

## 🚀 Instalación (desarrollo)

**Requisitos:** PHP 8.3+, Composer, MySQL, Node.js y npm

```bash
git clone https://github.com/luisfernando2607/SolarFix.git
cd SolarFix
composer install
npm install
cp .env.example .env          # configura DB_* y APP_TIMEZONE
php artisan key:generate
php artisan migrate --seed
npm run build                 # o `npm run dev` mientras desarrollas
php artisan serve
```

El seeder crea la sucursal, el usuario administrador, los catálogos (accesorios, marcas, modelos, checklists) y, fuera de producción, datos de demostración. Se puede volver a ejecutar sin duplicar nada.

> **Usuario inicial:** `admin@solarfix.ec` / `password`. En producción la app obliga a cambiarla en el primer ingreso.

---

## 🌐 Puesta en producción

1. **Entorno** (`.env`): `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` con HTTPS, `APP_TIMEZONE=America/Guayaquil`, `SESSION_SECURE_COOKIE=true`. Si hay un proxy delante (Nginx, Cloudflare, el panel del hosting), `TRUSTED_PROXIES=*` para que la app detecte el HTTPS.
2. **Código y dependencias:**
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build        # obligatorio: public/build no está en git
   php artisan migrate --force
   php artisan db:seed --force    # catálogos y checklists base (en producción no carga datos de demo)
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
3. **Seguridad:** al entrar por primera vez la app obliga a cambiar la contraseña del administrador. Sirve el sitio solo por HTTPS (la app envía cabeceras de seguridad y HSTS).
4. **`APP_KEY`:** guárdala en un lugar seguro y no la cambies; cifra el PIN/patrón de los equipos y sin ella no se pueden leer.
5. **Respaldos:** activa el programador de Laravel con este cron (cada minuto):
   ```
   * * * * * cd /ruta/a/SolarFix && php artisan schedule:run >> /dev/null 2>&1
   ```
   Así se ejecuta `php artisan solarfix:backup` todos los días a las 02:00: guarda en `storage/app/backups` un .zip con la base de datos (vía `mysqldump`) y las fotos y firmas, y conserva los últimos 14 (`BACKUP_KEEP`). **Copia fuera del servidor:** con `BACKUP_DISK=offsite` y `BACKUP_OFFSITE_PATH=/ruta/montada` (otro disco, un NAS o una carpeta sincronizada con la nube) cada respaldo se copia también allí. También se puede ejecutar a mano, con `--to=/otra/carpeta`, `--keep=30` o `--disk=offsite`.
6. **HTTPS** es necesario para que el celular ofrezca instalar la app.
7. **Revisión final:** `php artisan solarfix:check` (o **Estado del sistema** en el menú del admin) dice qué falta: modo producción, HTTPS, contraseñas por defecto, datos y RUC del negocio, cron, respaldos y copia externa. Mientras falte algo indispensable, el panel del admin muestra un aviso.

Las fotos y firmas son privadas: se sirven a través de la app con control de acceso. No hace falta `php artisan storage:link`.

### Actualizar una instalación existente

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --class=ChecklistSeeder --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Las migraciones son seguras sobre bases creadas con `docs/solarfix_schema.sql`: crean solo las tablas que faltan y sus `down()` no borran tablas con datos reales.

---

## 🧪 Pruebas

```bash
php artisan test
```

Los tests usan SQLite en memoria y no tocan la base real. Para correrlos contra MySQL, crea una base vacía y ejecuta `DB_CONNECTION=mysql DB_DATABASE=solarfix_test php vendor/bin/phpunit`.

Para probar a mano en el navegador, por módulo y por rol: [`docs/GUIA_DE_PRUEBAS.md`](docs/GUIA_DE_PRUEBAS.md).

---

## ⚠️ Limitaciones conocidas

- **Facturación electrónica SRI:** las facturas son comprobantes internos; no se envían al SRI.
- **WhatsApp:** los mensajes se abren en WhatsApp con el texto listo (wa.me); el envío automático requiere la API de WhatsApp Business.

---

## 📚 Documentación

- [Documento de procesos](docs/SolarFix_Documento_De_Procesos.md)
- [Guía de pruebas](docs/GUIA_DE_PRUEBAS.md)
- [Esquema original de base de datos](docs/solarfix_schema.sql) (referencia; el esquema real lo crean las migraciones)

---

<div align="center">

Desarrollado por **[Luis Fernando Flores](https://github.com/luisfernando2607)** · DevStar · Guayaquil, Ecuador 🇪🇨

</div>
