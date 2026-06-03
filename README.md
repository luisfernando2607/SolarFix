# SolarFix

Sistema de gestión integral para taller de reparaciones de dispositivos electrónicos y electrodomésticos.

## Funcionalidades

- **Órdenes de servicio**: Registro completo con asignación de cliente, dispositivo (marca/modelo), tipo de dispositivo, accesorios recibidos, fotos y seguimiento de estados (recibido, en diagnóstico, en reparación, esperando repuesto, listo, entregado, cancelado).
- **Clientes**: CRUD completo con soft deletes.
- **Catálogos**: Marcas, modelos y accesorios.
- **Usuarios**: CRUD con asignación de sucursal y roles (super_admin, admin, technician, receptionist).
- **Dispositivos**: Soporte para celulares, tablets, PCs, aires acondicionados (split/central), televisores, lavadores y otros.
- **Filtros y ordenamiento**: Tablas con filtro en vivo y ordenamiento por columna.

## Tecnologías

- **Backend**: Laravel 13.x, PHP 8.3
- **Base de datos**: MySQL
- **Frontend**: Blade templating, Tailwind CSS, Alpine.js, SweetAlert2
- **Autenticación**: Laravel Breeze / Sanctum

## Requisitos

- PHP 8.3+
- Composer
- MySQL
- Node.js & NPM

## Instalación

```bash
git clone <repo-url>
cd SolarFix
composer install
npm install
cp .env.example .env
# Configurar base de datos en .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

## Credenciales por defecto

- **Email**: admin@solarfix.ec
- **Contraseña**: password
