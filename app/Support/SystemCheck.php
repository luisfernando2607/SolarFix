<?php

namespace App\Support;

use App\Console\Commands\BackupCommand;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Revisión de lo necesario para usar la app con clientes reales: seguridad, HTTPS,
 * datos del negocio, programador (cron) y respaldos.
 * Cada punto: ok | warn | fail, con qué hacer si no está bien.
 */
class SystemCheck
{
    public static function run(): array
    {
        $production = app()->isProduction();
        $https = str_starts_with((string) config('app.url'), 'https://');
        $backups = BackupCommand::status();
        $heartbeat = storage_path('app/schedule-heartbeat');

        $checks = [
            self::item('Modo producción', $production, 'warn', 'APP_ENV=production en el .env del servidor.'),
            self::item('Errores ocultos al público', ! config('app.debug'), $production ? 'fail' : 'warn', 'APP_DEBUG=false: con true, un error muestra contraseñas y rutas del servidor.'),
            self::item('Clave de cifrado', filled(config('app.key')), 'fail', 'Ejecuta php artisan key:generate (y guárdala: sin ella no se leen los PIN cifrados).'),
            self::item('HTTPS', $https, $production ? 'fail' : 'warn', 'APP_URL debe empezar con https:// (el seguimiento y las firmas viajan por internet).'),
            self::item('Cookies solo por HTTPS', ! $https || config('session.secure'), 'warn', 'SESSION_SECURE_COOKIE=true cuando el sitio usa HTTPS.'),
            self::item('Sin contraseñas por defecto', ! self::defaultPasswordUsers(), 'fail', 'Usuarios con la contraseña "password": ' . implode(', ', self::defaultPasswordUsers()) . '.'),
            self::item('Administradores con verificación en dos pasos', ! self::adminsWithout2fa(), 'warn', 'Sin verificación en dos pasos: ' . implode(', ', self::adminsWithout2fa()) . '. Cada uno la activa en su menú; también se puede exigir en Configuración.'),
            self::item('Datos del negocio', Setting::get('company_name', 'SolarFix') !== 'SolarFix' && filled(Setting::get('company_phone')), 'warn', 'Completa nombre y teléfono en Configuración: salen en tickets, facturas y WhatsApp.'),
            self::item('RUC del negocio', filled(Setting::get('company_tax_id')), 'warn', 'Escribe el RUC en Configuración para que salga en facturas y comprobantes.'),
            self::item('Programador (cron)', is_file($heartbeat) && filemtime($heartbeat) > now()->subMinutes(15)->timestamp, 'fail', 'Agrega al cron del servidor: * * * * * cd ' . base_path() . ' && php artisan schedule:run'),
            self::item('Respaldo reciente', self::recent($backups['local'] ?? null, 2), 'fail', 'No hay respaldo de las últimas 48 horas. Revisa el cron o ejecuta php artisan solarfix:backup.'),
            self::item('Copia fuera del servidor', self::recent($backups['offsite'] ?? null, 2), 'warn', 'Configura BACKUP_DISK (y BACKUP_OFFSITE_PATH) para guardar una copia en otro equipo o en la nube.'),
            self::item('Archivos de la interfaz', is_file(public_path('build/manifest.json')), 'fail', 'Ejecuta npm run build.'),
            self::item('Carpeta de almacenamiento escribible', is_writable(storage_path('app')), 'fail', 'Da permisos de escritura a storage/ al usuario del servidor web.'),
        ];

        return $checks;
    }

    /**
     * ¿Hay algo que impida usarla en producción?
     */
    public static function hasFailures(): bool
    {
        return collect(self::run())->contains('status', 'fail');
    }

    private static function item(string $label, bool $ok, string $severity, string $help): array
    {
        return ['label' => $label, 'status' => $ok ? 'ok' : $severity, 'help' => $ok ? null : $help];
    }

    private static function recent(?string $iso, int $days): bool
    {
        return $iso && Carbon::parse($iso)->gt(now()->subDays($days));
    }

    private static function adminsWithout2fa(): array
    {
        return User::where('is_active', true)->whereIn('role', ['super_admin', 'admin'])
            ->whereNull('two_factor_confirmed_at')->pluck('name')->all();
    }

    /**
     * Nombres de usuarios activos que siguen con la contraseña "password".
     */
    private static function defaultPasswordUsers(): array
    {
        static $names = null;

        return $names ??= User::where('is_active', true)->get(['name', 'password'])
            ->filter(fn ($u) => Hash::check('password', $u->password))
            ->pluck('name')->all();
    }
}
