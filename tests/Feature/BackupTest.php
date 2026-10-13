<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BackupTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/solarfix-backup-test-' . uniqid();
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        File::delete(storage_path(\App\Console\Commands\BackupCommand::STATUS_FILE));
        parent::tearDown();
    }

    private function useSqliteFile(): void
    {
        $db = $this->dir . '/db.sqlite';
        File::put($db, 'contenido de prueba');
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $db]);
    }

    public function test_backup_contains_database_photos_and_signatures(): void
    {
        $this->useSqliteFile();
        Storage::fake('local');
        Storage::disk('local')->put('order_photos/a.jpg', 'foto');
        Storage::disk('local')->put('signatures/1-delivery.png', 'firma');
        Storage::disk('local')->put('otra_carpeta/no.txt', 'no se respalda');

        $this->artisan('solarfix:backup', ['--to' => $this->dir . '/out'])
            ->expectsOutputToContain('Respaldo creado')
            ->assertSuccessful();

        $zip = new ZipArchive();
        $zip->open(File::glob($this->dir . '/out/solarfix-*.zip')[0]);
        $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->sort()->values()->all();
        $this->assertSame(['database.sqlite', 'storage/order_photos/a.jpg', 'storage/signatures/1-delivery.png'], $names);
        $this->assertSame('contenido de prueba', $zip->getFromName('database.sqlite'));
    }

    public function test_old_backups_are_pruned(): void
    {
        $this->useSqliteFile();
        Storage::fake('local');
        $out = $this->dir . '/out';
        File::ensureDirectoryExists($out);
        foreach (['20260101-020000', '20260102-020000', '20260103-020000'] as $stamp) {
            File::put("{$out}/solarfix-{$stamp}.zip", 'viejo');
        }

        $this->artisan('solarfix:backup', ['--to' => $out, '--keep' => 2])->assertSuccessful();

        $remaining = collect(File::glob($out . '/solarfix-*.zip'))->map(fn ($p) => basename($p))->values();
        $this->assertCount(2, $remaining);
        $this->assertNotContains('solarfix-20260101-020000.zip', $remaining);
        $this->assertNotContains('solarfix-20260102-020000.zip', $remaining);
    }

    public function test_fails_clearly_when_database_cannot_be_copied(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);

        $this->artisan('solarfix:backup', ['--to' => $this->dir . '/out'])
            ->expectsOutputToContain('Respaldo fallido')
            ->assertFailed();

        $this->assertEmpty(File::glob($this->dir . '/out/*.zip'));
    }

    public function test_backup_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('solarfix:backup')->assertSuccessful();
    }

    public function test_backup_is_copied_offsite_and_status_recorded(): void
    {
        $this->useSqliteFile();
        Storage::fake('local');
        Storage::fake('offsite');
        File::delete(storage_path(\App\Console\Commands\BackupCommand::STATUS_FILE));

        $this->artisan('solarfix:backup', ['--to' => $this->dir . '/out', '--disk' => 'offsite'])
            ->expectsOutputToContain('Copia enviada')
            ->assertSuccessful();

        $this->assertCount(1, Storage::disk('offsite')->files('solarfix-backups'));
        $status = \App\Console\Commands\BackupCommand::status();
        $this->assertArrayHasKey('local', $status);
        $this->assertArrayHasKey('offsite', $status);
        File::delete(storage_path(\App\Console\Commands\BackupCommand::STATUS_FILE));
    }

    public function test_restore_brings_back_database_and_files(): void
    {
        $this->useSqliteFile();
        Storage::fake('local');
        Storage::disk('local')->put('order_photos/a.jpg', 'foto original');
        $this->artisan('solarfix:backup', ['--to' => $this->dir . '/out'])->assertSuccessful();
        $zip = File::glob($this->dir . '/out/solarfix-*.zip')[0];

        // Después del respaldo cambian la base y las fotos.
        File::put(config('database.connections.sqlite.database'), 'datos nuevos');
        Storage::disk('local')->put('order_photos/a.jpg', 'foto cambiada');

        $this->artisan('solarfix:restore', ['file' => $zip, '--force' => true, '--no-migrate' => true, '--no-safety-backup' => true])
            ->expectsOutputToContain('Restauración completa')
            ->assertSuccessful();

        $this->assertSame('contenido de prueba', File::get(config('database.connections.sqlite.database')));
        $this->assertSame('foto original', Storage::disk('local')->get('order_photos/a.jpg'));
    }

    public function test_restore_rejects_invalid_files_and_asks_confirmation(): void
    {
        $this->useSqliteFile();
        File::put($this->dir . '/malo.zip', 'no es un zip');
        $this->artisan('solarfix:restore', ['file' => $this->dir . '/malo.zip', '--force' => true])->assertFailed();

        Storage::fake('local');
        $this->artisan('solarfix:backup', ['--to' => $this->dir . '/out'])->assertSuccessful();
        $this->artisan('solarfix:restore', ['file' => File::glob($this->dir . '/out/solarfix-*.zip')[0], '--no-safety-backup' => true])
            ->expectsConfirmation('¿Continuar con la restauración?', 'no')
            ->assertFailed();
        $this->assertSame('contenido de prueba', File::get(config('database.connections.sqlite.database')));
    }
}
