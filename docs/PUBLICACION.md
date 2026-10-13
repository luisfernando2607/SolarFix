# Publicar SolarFix en internet

Guía paso a paso para dos tipos de servidor. Elige **una** de las dos opciones.

| | **A. VPS** (DigitalOcean, Contabo, Hetzner, Vultr…) | **B. Hosting con cPanel** |
|---|---|---|
| Costo aproximado | 5–12 USD/mes | 3–10 USD/mes |
| Control | Total (tú instalas todo) | Limitado al panel |
| Recomendado si | Quieres rendimiento y crecer a varias sucursales | Ya tienes un hosting o prefieres no administrar un servidor |
| Requisitos clave | Ubuntu 24.04 | PHP 8.3, MySQL, acceso **Terminal/SSH**, cron y SSL |

> Antes de empezar: compra o elige el **dominio** (ej. `taller.midominio.com`) y ten a mano el respaldo de tu base local si quieres llevar tus datos (`php artisan solarfix:backup`).

---

## A. VPS con Ubuntu 24.04

Todos los comandos se ejecutan en el servidor por SSH (`ssh root@IP-DEL-SERVIDOR`).

### 1. Programas

```bash
apt update && apt upgrade -y
apt install -y nginx mysql-server git unzip curl \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl php8.3-exif
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
curl -fsSL https://deb.nodesource.com/setup_22.x | bash - && apt install -y nodejs
```

Fotos desde el celular: en `/etc/php/8.3/fpm/php.ini` pon `upload_max_filesize = 12M` y `post_max_size = 64M`, y luego `systemctl restart php8.3-fpm`.

### 2. Base de datos

```bash
mysql -e "CREATE DATABASE solarfix CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'solarfix'@'localhost' IDENTIFIED BY 'UNA-CLAVE-LARGA-Y-UNICA';
GRANT ALL PRIVILEGES ON solarfix.* TO 'solarfix'@'localhost'; FLUSH PRIVILEGES;"
```

### 3. Código

```bash
cd /var/www && git clone https://github.com/luisfernando2607/SolarFix.git solarfix && cd solarfix
cp .env.example .env
```

Edita `.env` (`nano .env`):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://taller.midominio.com
APP_TIMEZONE=America/Guayaquil
DB_DATABASE=solarfix
DB_USERNAME=solarfix
DB_PASSWORD=UNA-CLAVE-LARGA-Y-UNICA
SESSION_SECURE_COOKIE=true
LOG_CHANNEL=daily
LOG_LEVEL=warning
```

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate          # guarda APP_KEY en un lugar seguro: cifra los PIN de los equipos
php artisan migrate --force
php artisan db:seed --force       # catálogos, checklists y el usuario admin@solarfix.ec / password
chown -R www-data:www-data storage bootstrap/cache
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

¿Llevas tus datos de la instalación local? Copia el .zip al servidor (`scp solarfix-*.zip root@IP:/var/www/solarfix/`) y ejecuta `php artisan solarfix:restore /var/www/solarfix/solarfix-AAAAMMDD-HHMMSS.zip` **en lugar del** `db:seed`.

### 4. Nginx y HTTPS

```bash
cp docs/deploy/nginx-solarfix.conf /etc/nginx/sites-available/solarfix
nano /etc/nginx/sites-available/solarfix      # cambia server_name por tu dominio
ln -s /etc/nginx/sites-available/solarfix /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
apt install -y certbot python3-certbot-nginx
certbot --nginx -d taller.midominio.com       # HTTPS gratis, se renueva solo
ufw allow OpenSSH && ufw allow 'Nginx Full' && ufw --force enable
```

En el proveedor del dominio, crea un registro **A** que apunte `taller.midominio.com` a la IP del servidor (antes de `certbot`).

### 5. Cron (respaldos y alertas)

```bash
crontab -u www-data -e
```

Agrega la línea:

```
* * * * * cd /var/www/solarfix && php artisan schedule:run >> /dev/null 2>&1
```

### 6. Actualizar más adelante

```bash
cd /var/www/solarfix && sudo -u www-data bash docs/deploy/actualizar.sh
```

Hace un respaldo, pone la app en mantenimiento, baja la última versión, actualiza dependencias y base de datos, y la vuelve a abrir.

---

## B. Hosting con cPanel

Necesitas que el plan tenga **PHP 8.3**, **Terminal** (o acceso SSH) y **Cron Jobs**. Sin Terminal se puede, pero es mucho más trabajoso: pídele al hosting que lo active.

### 1. PHP

**Select PHP Version** (o *MultiPHP Manager*) → 8.3, con las extensiones `mbstring, xml, curl, zip, gd, pdo_mysql, bcmath, intl, exif, fileinfo`. En **MultiPHP INI Editor**: `upload_max_filesize = 12M`, `post_max_size = 64M`.

### 2. Base de datos

**MySQL Databases** → crea la base (ej. `usuario_solarfix`) y un usuario con clave larga, y dale **ALL PRIVILEGES** sobre la base.

### 3. Subdominio apuntando a `public`

**Domains** (o *Subdomains*) → crea `taller.midominio.com` con **Document Root** = `solarfix/public`. Es importante: si apunta a la carpeta de la app y no a `public`, el `.env` quedaría expuesto.

### 4. Código

En **Terminal**:

```bash
cd ~ && git clone https://github.com/luisfernando2607/SolarFix.git solarfix && cd solarfix
cp .env.example .env && nano .env      # mismos valores que en el VPS (paso A.3), con la base y usuario de cPanel
composer install --no-dev --optimize-autoloader     # si no existe "composer": php ~/composer.phar …
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Interfaz (CSS/JS):** la mayoría de hostings no tiene Node. Compílala en tu computadora (`npm run build`) y sube la carpeta `public/build` completa a `solarfix/public/build` con el **File Manager** (o `scp`).

### 5. HTTPS

**SSL/TLS Status** → *Run AutoSSL* para el subdominio. Si hay una opción "Forzar HTTPS", actívala.

### 6. Cron

**Cron Jobs** → *Once Per Minute* (`* * * * *`) con el comando:

```
cd /home/TU-USUARIO-CPANEL/solarfix && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

(La ruta de `php` puede variar; el hosting la muestra en *Cron Jobs* o con `which php` en Terminal.)

### 7. Actualizar más adelante

En Terminal: `cd ~/solarfix && bash docs/deploy/actualizar.sh`. Si no hay Node, sube antes `public/build` compilado en tu equipo.

---

## Después de publicar (cualquier opción)

1. Entra con `admin@solarfix.ec` / `password` → la app pide **cambiar la contraseña**.
2. **Configuración**: nombre, RUC, teléfono, dirección y **logo**; IVA; textos de WhatsApp.
3. Menú → **Verificación en dos pasos** en tu cuenta; guarda los códigos de respaldo.
4. **Usuarios**: crea técnicos y recepción (cada uno cambiará su contraseña al entrar).
5. **Importar datos**: clientes y repuestos desde Excel, si los tienes.
6. **Correo** (opcional): datos SMTP en el `.env` → *Configuración → Enviarme un correo de prueba*.
7. **Respaldos fuera del servidor**: `BACKUP_DISK=offsite` y `BACKUP_OFFSITE_PATH`, o descarga cada semana un .zip de `storage/app/backups`.
8. **Prueba una restauración** con un respaldo real (mejor en una copia de prueba): un respaldo que nunca se restauró no es confiable.
9. **Estado del sistema** (menú del admin) o `php artisan solarfix:check`: todo en "Listo".
10. Comparte con tus clientes la página de citas: `https://taller.midominio.com/agendar`.
