<?php

namespace App\Services;

use App\Models\Client;
use App\Models\SparePart;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Carga inicial desde Excel (guardado como CSV): clientes y repuestos.
 * Acepta separador ";" o ",", con o sin BOM, y encabezados en español con o sin tildes.
 * Cada fila se procesa por separado: una fila con error no detiene las demás.
 */
class CsvImport
{
    public const MAX_ROWS = 5000;

    public const COLUMNS = [
        'clients' => ['nombre', 'cedula', 'telefono', 'email', 'direccion'],
        'parts' => ['nombre', 'codigo', 'categoria', 'costo', 'precio', 'stock_minimo', 'stock', 'ubicacion'],
    ];

    private const ALIASES = [
        'name' => 'nombre', 'cliente' => 'nombre', 'repuesto' => 'nombre', 'producto' => 'nombre', 'descripcion' => 'nombre',
        'ruc' => 'cedula', 'documento' => 'cedula', 'identificacion' => 'cedula', 'cedula_ruc' => 'cedula',
        'celular' => 'telefono', 'phone' => 'telefono', 'whatsapp' => 'telefono',
        'correo' => 'email', 'mail' => 'email', 'correo_electronico' => 'email',
        'address' => 'direccion',
        'code' => 'codigo', 'sku' => 'codigo', 'codigo_de_barras' => 'codigo',
        'category' => 'categoria',
        'precio_costo' => 'costo', 'costo_unitario' => 'costo', 'cost' => 'costo',
        'precio_venta' => 'precio', 'pvp' => 'precio', 'price' => 'precio',
        'minimo' => 'stock_minimo', 'stock_min' => 'stock_minimo',
        'cantidad' => 'stock', 'existencia' => 'stock', 'existencias' => 'stock',
        'location' => 'ubicacion',
    ];

    /**
     * Filas del archivo como arreglos [columna => valor] (encabezados normalizados).
     */
    public function read(UploadedFile $file): array
    {
        $content = (string) file_get_contents($file->getRealPath());
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252'); // Excel en Windows
        }

        $lines = preg_split('/\r\n|\n|\r/', trim($content));
        $delimiter = substr_count($lines[0] ?? '', ';') >= substr_count($lines[0] ?? '', ',') ? ';' : ',';
        $header = array_map(fn ($h) => $this->column($h), str_getcsv(array_shift($lines) ?? '', $delimiter));

        if (! in_array('nombre', $header, true)) {
            throw new DomainException('El archivo debe tener una columna "nombre" en la primera fila.');
        }
        if (count($lines) > self::MAX_ROWS) {
            throw new DomainException('Máximo '.self::MAX_ROWS.' filas por archivo.');
        }

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line, " \t;,") === '') {
                continue;
            }
            $values = array_map('trim', str_getcsv($line, $delimiter));
            $rows[] = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), ''));
        }

        return $rows;
    }

    /**
     * Crea los clientes nuevos; los que ya existen (misma cédula o teléfono) se omiten.
     */
    public function clients(array $rows): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        $phones = [];
        $documents = [];
        Client::select(['id', 'phone', 'id_document'])->lazyById(2000)->each(function ($c) use (&$phones, &$documents) {
            if ($p = WhatsAppMessage::normalizePhone($c->phone)) {
                $phones[$p] = true;
            }
            if ($d = preg_replace('/\D/', '', (string) $c->id_document)) {
                $documents[$d] = true;
            }
        });

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $name = trim($row['nombre'] ?? '');
            if ($name === '') {
                $result['errors'][] = "Fila {$line}: falta el nombre.";

                continue;
            }
            if (trim($row['telefono'] ?? '') === '') {
                $result['errors'][] = "Fila {$line}: falta el teléfono de {$name}.";

                continue;
            }
            $email = trim($row['email'] ?? '');
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $result['errors'][] = "Fila {$line}: el email «{$email}» no es válido.";

                continue;
            }

            $phone = WhatsAppMessage::normalizePhone($row['telefono'] ?? '');
            $document = preg_replace('/\D/', '', (string) ($row['cedula'] ?? ''));
            if (($phone && isset($phones[$phone])) || ($document && isset($documents[$document]))) {
                $result['skipped']++;

                continue;
            }

            try {
                Client::create([
                    'name' => Str::limit($name, 150, ''),
                    'id_document' => Str::limit(trim($row['cedula'] ?? ''), 20, '') ?: null,
                    'phone' => Str::limit(trim($row['telefono'] ?? ''), 30, '') ?: null,
                    'email' => $email ?: null,
                    'address' => Str::limit(trim($row['direccion'] ?? ''), 255, '') ?: null,
                    'client_type' => strlen($document) === 13 ? 'business' : 'individual',
                ]);
            } catch (\Throwable $e) {
                report($e);
                $result['errors'][] = "Fila {$line}: no se pudo guardar.";

                continue;
            }
            $phone && $phones[$phone] = true;
            $document && $documents[$document] = true;
            $result['created']++;
        }

        return $result;
    }

    /**
     * Crea repuestos o actualiza los que tienen el mismo código; el stock indicado queda como
     * conteo inicial en la sucursal (con su movimiento de ajuste).
     */
    public function parts(array $rows, int $branchId, ?int $userId): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $inventory = app(InventoryService::class);

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $name = trim($row['nombre'] ?? '');
            if ($name === '') {
                $result['errors'][] = "Fila {$line}: falta el nombre.";

                continue;
            }

            $numbers = [];
            foreach (['costo' => 'costo', 'precio' => 'precio', 'stock_minimo' => 'stock mínimo', 'stock' => 'stock'] as $key => $label) {
                $raw = trim($row[$key] ?? '');
                if ($raw === '') {
                    $numbers[$key] = null;

                    continue;
                }
                $value = $this->number($raw);
                if ($value === null || $value < 0) {
                    $result['errors'][] = "Fila {$line}: el {$label} «{$raw}» no es un número válido.";

                    continue 2;
                }
                $numbers[$key] = $value;
            }

            try {
                DB::transaction(function () use ($row, $name, $numbers, $branchId, $userId, $inventory, &$result) {
                    $code = Str::limit(trim($row['codigo'] ?? ''), 60, '') ?: null;
                    $part = $code ? SparePart::where('code', $code)->first() : null;
                    $attributes = array_filter([
                        'name' => Str::limit($name, 150, ''),
                        'category' => Str::limit(trim($row['categoria'] ?? ''), 100, '') ?: null,
                        'location' => Str::limit(trim($row['ubicacion'] ?? ''), 100, '') ?: null,
                        'cost_price' => $numbers['costo'],
                        'sale_price' => $numbers['precio'],
                        'min_stock' => $numbers['stock_minimo'] !== null ? (int) $numbers['stock_minimo'] : null,
                    ], fn ($v) => $v !== null);

                    if ($part) {
                        $part->update($attributes);
                        $result['updated']++;
                    } else {
                        $part = SparePart::create($attributes + ['code' => $code, 'cost_price' => 0, 'sale_price' => 0, 'min_stock' => 0, 'is_active' => true]);
                        $result['created']++;
                    }

                    if ($numbers['stock'] !== null) {
                        $inventory->adjust($part, (int) $numbers['stock'], 'Carga inicial desde archivo', $userId, $branchId);
                    }
                });
            } catch (\Throwable $e) {
                report($e);
                $result['errors'][] = "Fila {$line}: no se pudo guardar.";
            }
        }

        return $result;
    }

    private function column(string $header): string
    {
        $key = Str::of($header)->ascii()->lower()->trim()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value();

        return self::ALIASES[$key] ?? $key;
    }

    /**
     * "1.234,50", "1234.50" o "12,5" → número.
     */
    private function number(string $raw): ?float
    {
        $clean = preg_replace('/[^\d.,-]/', '', $raw);
        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = strrpos($clean, ',') > strrpos($clean, '.') ? str_replace(['.', ','], ['', '.'], $clean) : str_replace(',', '', $clean);
        } else {
            $clean = str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }
}
