<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Las fotos de los equipos pasan del disco público (accesible sin iniciar sesión)
 * al privado; se sirven a través de la app. La ruta relativa no cambia.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->move(Storage::disk('public'), Storage::disk('local'));
    }

    public function down(): void
    {
        $this->move(Storage::disk('local'), Storage::disk('public'));
    }

    private function move($from, $to): void
    {
        DB::table('order_photos')->orderBy('id')->each(function ($photo) use ($from, $to) {
            if ($from->exists($photo->file_path) && ! $to->exists($photo->file_path)) {
                $to->writeStream($photo->file_path, $from->readStream($photo->file_path));
                $from->delete($photo->file_path);
            }
        });
    }
};
