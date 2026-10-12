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
| 📦 **Inventario** | Repuestos con stock, costo promedio, entradas por proveedor, ajustes y alerta de stock bajo; se descuentan al usarlos en una orden |
| 💵 **Cobros y caja** | Pagos por orden, comprobantes, caja del día con fondo inicial, gastos, retiros y cuadre |
| 🧾 **Facturas** | Desde la orden, con IVA configurable (incluido o no en los precios) |
| 🛡️ **Garantías** | Emitidas al entregar; el reclamo abre una orden vinculada y la cobertura (completa, mano de obra o repuestos) define qué se cobra |
| 📅 **Agenda** | Visitas a domicilio con choque de horarios, confirmación y recordatorio |
| 🔁 **Mantenimientos** | Planes preventivos por cliente que se renuevan al completar la visita |
| 💰 **Comisiones** | Fijas o porcentuales por técnico, generadas al entregar; adelantos que se descuentan al pagar |
| 📊 **Reportes** | Ingresos, órdenes, técnicos, tipos de equipo y repuestos; exportación a Excel |
| ⚙️ **Configuración** | Datos del negocio, IVA, prefijos de orden y factura, moneda, métodos de pago, textos de WhatsApp, alertas y comisiones |
| 🔔 **Notificaciones** | Aviso al técnico cuando le asignan una orden, una cita o un reclamo |
| 🕵️ **Auditoría** | Quién cambió qué y cuándo (valor anterior y nuevo) e intentos de acceso |

### Roles

| Rol | Puede |
|---|---|
| `super_admin`, `admin` | Todo: usuarios, catálogos, configuración, reportes, auditoría, comisiones, precios y ajustes de inventario, eliminar órdenes, pagos y facturas. |
| `receptionist` | Clientes, órdenes, cobros, facturas, caja, garantías, agenda y mantenimientos. |
| `technician` | Sus órdenes y las sin asignar, repuestos y entradas de inventario, sus citas y sus comisiones. No ve facturas ni caja. |

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

> **Usuario inicial:** `admin@solarfix.ec` / `password` — cámbialo al entrar.

---

## 🌐 Puesta en producción

1. **Entorno** (`.env`): `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` con HTTPS, `APP_TIMEZONE=America/Guayaquil`.
2. **Código y dependencias:**
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build        # obligatorio: public/build no está en git
   php artisan migrate --force
   php artisan db:seed --force    # catálogos y checklists base (en producción no carga datos de demo)
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
3. **Seguridad:** cambia la contraseña del administrador y sirve el sitio solo por HTTPS.
4. **`APP_KEY`:** guárdala en un lugar seguro y no la cambies; cifra el PIN/patrón de los equipos y sin ella no se pueden leer.
5. **Respaldos diarios** de la base de datos y de `storage/app/private` (fotos de los equipos y firmas de los clientes).

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
- **Multi-sucursal:** los usuarios tienen sucursal, pero órdenes, caja e inventario funcionan como una sola sucursal.

---

## 📚 Documentación

- [Documento de procesos](docs/SolarFix_Documento_De_Procesos.md)
- [Guía de pruebas](docs/GUIA_DE_PRUEBAS.md)
- [Esquema original de base de datos](docs/solarfix_schema.sql) (referencia; el esquema real lo crean las migraciones)

---

<div align="center">

Desarrollado por **[Luis Fernando Flores](https://github.com/luisfernando2607)** · DevStar · Guayaquil, Ecuador 🇪🇨

</div>
