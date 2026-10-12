# SolarFix

Sistema de gestión para talleres de reparación de celulares, computadoras, aires acondicionados, televisores y electrodomésticos: recepción, diagnóstico, reparación, entrega, cobro, garantías, inventario y visitas a domicilio.

Implementa los flujos de `docs/SolarFix_Documento_De_Procesos.md`. Para verificar todo a mano, sigue `docs/GUIA_DE_PRUEBAS.md`.

## Funcionalidades

**Órdenes de servicio**
- Recepción con cliente (búsqueda o alta rápida), equipo, accesorios, fotos, falla declarada y PIN/patrón (guardado cifrado).
- Estados: recibido, en diagnóstico, esperando aprobación, en reparación, listo, entregado, cerrado sin reparación, en garantía.
- Técnico asignado: cada técnico ve sus órdenes y las que no tienen técnico asignado.
- Checklists de recepción, diagnóstico y control de calidad por tipo de equipo.
- Repuestos del inventario con precio de venta automático y descuento de stock.
- Firma digital del cliente al recibir y al entregar (o al devolver sin reparar).
- PDF de la orden con costos, repuestos, checklist de recepción, garantía y firmas.
- WhatsApp (enlaces wa.me): presupuesto, equipo listo y entrega con garantía.

**Dinero**
- Pagos por orden (efectivo, transferencia, tarjeta); el saldo se calcula desde los pagos.
- Facturas desde la orden, con IVA configurable e incluido o no en los precios.
- Caja del día: cobros por método, fondo inicial, gastos, retiros, cuadre con justificación de diferencias.
- Comisiones de técnicos (fijas o porcentuales) generadas al entregar.
- Reportes por período, con exportación a Excel (CSV).

**Posventa**
- Garantías emitidas al entregar, con reclamo que abre una orden vinculada sin costo.
- Agenda de visitas a domicilio con detección de choques de horario, confirmación y recordatorio por WhatsApp.
- Mantenimientos preventivos recurrentes: planes por cliente, aviso y agenda; se renuevan al completar la visita.

**Administración**
- Usuarios y roles, catálogos (marcas, modelos, accesorios, checklists), configuración del negocio.
- Auditoría de cambios (quién, cuándo, valor anterior y nuevo) e intentos de acceso.

### Roles

| Rol | Puede |
|---|---|
| `super_admin`, `admin` | Todo: usuarios, catálogos, configuración, reportes, auditoría, comisiones, precios y ajustes de inventario, eliminar órdenes y facturas. |
| `receptionist` | Clientes, órdenes, facturas, caja, garantías, agenda y mantenimientos. |
| `technician` | Sus órdenes y las sin asignar, repuestos y entradas de inventario, sus citas y sus comisiones. No ve facturas ni caja. |

## Tecnologías

- Laravel 13 (PHP 8.3), MySQL (los tests usan SQLite en memoria).
- Blade + Tailwind CSS 4 (Vite), SweetAlert2, DomPDF.
- Sin dependencias de JavaScript propias: el código de las vistas es JavaScript sin frameworks.

## Instalación (desarrollo)

```bash
git clone <repo-url>
cd SolarFix
composer install
npm install
cp .env.example .env          # configurar DB_* y APP_TIMEZONE
php artisan key:generate
php artisan migrate --seed
npm run build                 # o `npm run dev` mientras desarrollas
php artisan serve
```

El seeder crea la sucursal, el usuario administrador, los catálogos (accesorios, marcas, modelos, checklists) y, si no hay órdenes, datos de demostración. Se puede volver a ejecutar sin duplicar nada.

**Credenciales iniciales:** `admin@solarfix.ec` / `password` — cámbialas al entrar.

## Puesta en producción

1. **Entorno** (`.env`): `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` con HTTPS, `APP_TIMEZONE=America/Guayaquil`.
2. **Código y dependencias:**
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build        # obligatorio: public/build no está en git
   php artisan migrate --force
   php artisan db:seed --force    # catálogos y checklists base (idempotente)
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
3. **Seguridad:** cambia la contraseña del administrador y sirve el sitio solo por HTTPS.
4. **`APP_KEY`:** guárdala en un lugar seguro y no la cambies; cifra el PIN/patrón de los equipos y sin ella no se pueden leer.
5. **Respaldos diarios** de:
   - la base de datos,
   - `storage/app/private` (fotos de los equipos y firmas de los clientes).

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

Las migraciones son seguras sobre bases creadas con el script `docs/solarfix_schema.sql`: crean solo las tablas que faltan, y sus `down()` no borran tablas con datos reales.

## Base de datos

El esquema completo se crea con migraciones. `docs/solarfix_schema.sql` queda solo como referencia del diseño original.

## Pruebas

```bash
php artisan test
```

Los tests usan SQLite en memoria y no tocan la base de datos real. Para correrlos contra MySQL, crea una base vacía y ejecuta:

```bash
DB_CONNECTION=mysql DB_DATABASE=solarfix_test php vendor/bin/phpunit
```

## Limitaciones conocidas

- **Facturación electrónica SRI:** las facturas son comprobantes internos; no se envían al SRI.
- **WhatsApp:** los mensajes se abren en WhatsApp con el texto listo (wa.me); el envío automático requiere la API de WhatsApp Business.
- **Multi-sucursal:** los usuarios tienen sucursal, pero órdenes, caja e inventario funcionan como una sola sucursal.
