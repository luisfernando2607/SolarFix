<div align="center">

# 🔧 SolarFix

**Sistema de gestión integral para talleres de reparación**

Celulares · Tablets · PCs · Aires acondicionados · Televisores · Electrodomésticos

![Laravel](https://img.shields.io/badge/Laravel_13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP_8.3-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Tailwind](https://img.shields.io/badge/Tailwind-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)
![Alpine.js](https://img.shields.io/badge/Alpine.js-8BC0D0?style=for-the-badge&logo=alpinedotjs&logoColor=black)

<img src="docs/WhatsApp%20Image%202026-05-29%20at%202.52.04%20PM.jpeg" alt="Orden de servicio en SolarFix" width="90%"/>

</div>

---

## 🛠️ El problema

Los talleres suelen registrar equipos en cuadernos o chats de WhatsApp: se pierden accesorios, nadie sabe en qué estado está una reparación y el cobro de saldos es un caos.

**SolarFix** centraliza la recepción, el diagnóstico, la reparación y la entrega en un solo flujo trazable.

---

## ✨ Funcionalidades

| Módulo | Qué hace |
|---|---|
| 📋 **Órdenes de servicio** | Cliente, equipo (marca/modelo/IMEI), accesorios recibidos, fotos, patrón/PIN y falla declarada |
| 🔄 **Seguimiento de estados** | Recibido → En diagnóstico → En reparación → Esperando repuesto → Listo → Entregado / Cancelado |
| 💵 **Cobros** | Presupuesto, abonos, recargos y saldo pendiente por orden |
| 🖨️ **Comprobantes** | Impresión en PDF y envío por WhatsApp |
| 👥 **Clientes** | CRUD completo con borrado lógico (soft deletes) |
| 🗂️ **Catálogos** | Marcas, modelos, tipos de equipo y accesorios |
| 🔐 **Usuarios y roles** | super_admin, admin, técnico y recepcionista, con asignación de sucursal |
| 🔎 **Tablas** | Filtro en vivo y ordenamiento por columna |

---

## 🧰 Stack

| Capa | Tecnología |
|---|---|
| Backend | Laravel 13 · PHP 8.3 |
| Frontend | Blade · Tailwind CSS · Alpine.js · SweetAlert2 |
| Base de datos | MySQL |
| Autenticación | Laravel Breeze / Sanctum |

---

## 🚀 Instalación

**Requisitos:** PHP 8.3+, Composer, MySQL, Node.js y npm

```bash
git clone https://github.com/luisfernando2607/SolarFix.git
cd SolarFix
composer install
npm install
cp .env.example .env          # configura la base de datos
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

> **Usuario de demo** (creado por el seeder): `admin@solarfix.ec` / `password` — cámbialo antes de usarlo en producción.

---

## 📚 Documentación

- [Documento técnico](docs/SolarFix_Documento_Tecnico.md)
- [Documento de procesos](docs/SolarFix_Documento_De_Procesos.md)
- [Esquema de base de datos](docs/solarfix_schema.sql)

---

<div align="center">

Desarrollado por **[Luis Fernando Flores](https://github.com/luisfernando2607)** · DevStar · Guayaquil, Ecuador 🇪🇨

</div>
