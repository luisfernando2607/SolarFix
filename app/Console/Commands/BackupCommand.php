<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Respaldo en un .zip: base de datos + almacenamiento privado (fotos y firmas).
 * Conserva los últimos N respaldos. Programado a diario en routes/console.php.
 */
class BackupCommand extends Command
{
    protected $signature = 'solarfix:backup
        {--to= : Carpeta destino (por defecto storage/app/backups)}
        {--keep=14 : Cuántos respaldos conservar}';

    protected $description = 'Respalda la base de datos, las fotos y las firmas en un archivo .zip';

    public function handle(): int
    {
        $destination = $this->option('to') ?: storage_path('app/backups');
        $keep = max(1, (int) $this->option('keep'));
        File::ensureDirectoryExists($destination);

        $zipPath = $destination . '/solarfix-' . now()->format('Ymd-His') . '.zip';
        $tmpDir = sys_get_temp_dir() . '/solarfix-backup-' . uniqid();
        File::ensureDirectoryExists($tmpDir);

        try {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException("No se pudo crear {$zipPath}");
            }

            $dbFile = $this->dumpDatabase($tmpDir);
            $zip->addFile($dbFile, basename($dbFile));

            $files = $this->addPrivateStorage($zip);
            $zip->close();
        } catch (\Throwable $e) {
            File::delete($zipPath);
            $this->error('Respaldo fallido: ' . $e->getMessage());

            return self::FAILURE;
        } finally {
            File::deleteDirectory($tmpDir);
        }

        $removed = $this->prune($destination, $keep);

        $this->info(sprintf('Respaldo creado: %s (%s, %d archivos privados).', $zipPath, $this->size(filesize($zipPath)), $files));
        if ($removed) {
            $this->line("Se eliminaron {$removed} respaldos antiguos (se conservan {$keep}).");
        }

        return self::SUCCESS;
    }

    private function dumpDatabase(string $tmpDir): string
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if ($config['driver'] === 'sqlite') {
            $path = $config['database'];
            if ($path === ':memory:' || ! is_file($path)) {
                throw new RuntimeException('La base SQLite no es un archivo que se pueda copiar.');
            }
            $target = $tmpDir . '/database.sqlite';
            File::copy($path, $target);

            return $target;
        }

        if (! in_array($config['driver'], ['mysql', 'mariadb'])) {
            throw new RuntimeException("Motor de base de datos no soportado: {$config['driver']}");
        }

        // La contraseña va en un archivo de opciones temporal, no en la línea de comandos
        // (sería visible en la lista de procesos).
        $options = $tmpDir . '/my.cnf';
        $quote = fn ($value) => '"' . addcslashes((string) $value, '"\\') . '"';
        File::put($options, "[client]\nuser={$quote($config['username'])}\npassword={$quote($config['password'])}\n"
            . "host={$quote($config['host'])}\nport=" . (int) $config['port'] . "\n");
        chmod($options, 0600);

        $target = $tmpDir . '/database.sql';
        $result = Process::timeout(600)->run([
            'mysqldump', '--defaults-extra-file=' . $options, '--single-transaction', '--routines',
            '--no-tablespaces', '--default-character-set=utf8mb4', '--result-file=' . $target, $config['database'],
        ]);

        if (! $result->successful() || ! is_file($target) || filesize($target) === 0) {
            throw new RuntimeException('mysqldump falló: ' . trim($result->errorOutput()));
        }

        return $target;
    }

    private function addPrivateStorage(ZipArchive $zip): int
    {
        $root = Storage::disk('local')->path('');
        $count = 0;

        foreach (['order_photos', 'signatures'] as $folder) {
            if (! is_dir($root . $folder)) {
                continue;
            }
            foreach (File::allFiles($root . $folder) as $file) {
                $zip->addFile($file->getPathname(), 'storage/' . $folder . '/' . $file->getRelativePathname());
                $count++;
            }
        }

        return $count;
    }

    private function prune(string $destination, int $keep): int
    {
        $backups = collect(File::glob($destination . '/solarfix-*.zip'))->sort()->values();
        $old = $backups->slice(0, max(0, $backups->count() - $keep));
        $old->each(fn ($path) => File::delete($path));

        return $old->count();
    }

    private function size(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : round($bytes / 1024, 1) . ' KB';
    }
}
