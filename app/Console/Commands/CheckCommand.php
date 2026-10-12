<?php

namespace App\Console\Commands;

use App\Support\SystemCheck;
use Illuminate\Console\Command;

class CheckCommand extends Command
{
    protected $signature = 'solarfix:check';

    protected $description = 'Revisa si la instalación está lista para usarse con clientes reales';

    public function handle(): int
    {
        $icons = ['ok' => 'OK', 'warn' => 'Aviso', 'fail' => 'Falta'];
        $checks = SystemCheck::run();

        $this->table(['Estado', 'Punto', 'Qué hacer'], array_map(fn ($c) => [$icons[$c['status']], $c['label'], $c['help'] ?? ''], $checks));

        $failures = collect($checks)->where('status', 'fail')->count();
        $failures
            ? $this->error("{$failures} punto(s) por resolver antes de usarla con clientes.")
            : $this->info('Lista para producción.');

        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
