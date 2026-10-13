<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Restaura un respaldo de solarfix:backup: base de datos, fotos y firmas.
 * Antes de tocar nada guarda un respaldo del estado actual (por si se eligió el archivo equivocado)
 * y al final aplica las migraciones pendientes (un respaldo viejo queda con la estructura actual).
 */
class RestoreCommand extends Command
{
    protected $signature = 'solarfix:restore
        {file? : Ruta del .zip (por defecto, el más reciente de storage/app/backups)}
        {--force : No pedir confirmación}
        {--no-safety-backup : No respaldar el estado actual antes de restaurar}
        {--no-migrate : No aplicar migraciones después de restaurar}';

    protected $description = 'Restaura la base de datos, las fotos y las firmas desde un respaldo .zip';

    public function handle(): int
    {
        $file = $this->argument('file') ?: collect(File::glob(storage_path('app/backups/solarfix-*.zip')))->sort()->last();
        if (! $file || ! is_file($file)) {
            $this->error('No se encontró el respaldo. Indica la ruta del .zip.');

            return self::FAILURE;
        }

        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            $this->error("No se pudo abrir {$file}: no es un .zip válido.");

            return self::FAILURE;
        }
        $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i));
        $dbEntry = $names->first(fn ($n) => in_array($n, ['database.sql', 'database.sqlite'], true));
        if (! $dbEntry) {
            $zip->close();
            $this->error('El respaldo no contiene la base de datos (database.sql o database.sqlite).');

            return self::FAILURE;
        }
        $storageFiles = $names->filter(fn ($n) => preg_match('#^storage/(order_photos|signatures)/[^/]#', $n) && ! str_contains($n, '..'));

        $this->info('Respaldo: ' . basename($file) . ' (' . round(filesize($file) / 1048576, 1) . ' MB)');
        $this->line("Contiene la base de datos y {$storageFiles->count()} fotos y firmas.");
        $this->warn('Se REEMPLAZARÁ la base de datos actual (' . config('database.connections.' . config('database.default') . '.database') . ').');
        if (! $this->option('force') && ! $this->confirm('¿Continuar con la restauración?')) {
            $zip->close();
            $this->line('Cancelado.');

            return self::FAILURE;
        }

        if (! $this->option('no-safety-backup')) {
            $this->line('Respaldando el estado actual antes de restaurar…');
            if (Artisan::call('solarfix:backup', ['--to' => storage_path('app/backups/antes-de-restaurar'), '--keep' => 5]) !== 0) {
                $zip->close();
                $this->error('No se pudo respaldar el estado actual; no se restauró nada. ' . trim(Artisan::output()));

                return self::FAILURE;
            }
        }

        $tmpDir = sys_get_temp_dir() . '/solarfix-restore-' . uniqid();
        File::ensureDirectoryExists($tmpDir);

        try {
            $zip->extractTo($tmpDir, $dbEntry);
            $this->restoreDatabase($tmpDir . '/' . $dbEntry);

            $root = Storage::disk('local')->path('');
            foreach ($storageFiles as $name) {
                $target = $root . substr($name, strlen('storage/'));
                File::ensureDirectoryExists(dirname($target));
                File::put($target, $zip->getFromName($name));
            }
        } catch (\Throwable $e) {
            $this->error('La restauración falló: ' . $e->getMessage());
            $this->line('El estado anterior quedó respaldado en storage/app/backups/antes-de-restaurar.');

            return self::FAILURE;
        } finally {
            $zip->close();
            File::deleteDirectory($tmpDir);
        }

        if (! $this->option('no-migrate')) {
            Artisan::call('migrate', ['--force' => true]);
        }
        Artisan::call('optimize:clear');

        $this->info("Restauración completa: base de datos y {$storageFiles->count()} fotos y firmas.");

        return self::SUCCESS;
    }

    private function restoreDatabase(string $dump): void
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if ($config['driver'] === 'sqlite') {
            if (! str_ends_with($dump, '.sqlite')) {
                throw new RuntimeException('El respaldo es de MySQL y esta instalación usa SQLite.');
            }
            File::copy($dump, $config['database']);

            return;
        }

        if (! in_array($config['driver'], ['mysql', 'mariadb'], true) || ! str_ends_with($dump, '.sql')) {
            throw new RuntimeException('El respaldo no corresponde al motor de base de datos de esta instalación.');
        }

        // La base se vacía antes de cargar el respaldo: no quedan tablas o filas que el respaldo no tenía.
        app('db')->connection()->getSchemaBuilder()->dropAllTables();

        $options = dirname($dump) . '/my.cnf';
        $quote = fn ($value) => '"' . addcslashes((string) $value, '"\\') . '"';
        File::put($options, "[client]\nuser={$quote($config['username'])}\npassword={$quote($config['password'])}\n"
            . "host={$quote($config['host'])}\nport=" . (int) $config['port'] . "\n");
        chmod($options, 0600);

        $result = Process::timeout(1800)->input(fopen($dump, 'r'))->run([
            'mysql', '--defaults-extra-file=' . $options, '--default-character-set=utf8mb4', $config['database'],
        ]);
        if (! $result->successful()) {
            throw new RuntimeException('mysql falló: ' . trim($result->errorOutput()));
        }
        app('db')->purge($connection);
    }
}
