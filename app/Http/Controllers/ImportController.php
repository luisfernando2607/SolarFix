<?php

namespace App\Http\Controllers;

use App\Services\CsvImport;
use App\Support\BranchContext;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Importar clientes y repuestos desde Excel (CSV) para empezar sin cargar todo a mano.
 */
class ImportController extends Controller
{
    public function index()
    {
        return view('import.index', ['columns' => CsvImport::COLUMNS]);
    }

    /**
     * Plantilla con los encabezados y una fila de ejemplo.
     */
    public function template(string $type)
    {
        abort_unless(isset(CsvImport::COLUMNS[$type]), 404);
        $example = [
            'clients' => ['María Pérez', '0912345678', '0991234567', 'maria@correo.com', 'Av. Siempre Viva 123'],
            'parts' => ['Pantalla Samsung A15', 'PAN-A15', 'Pantallas', '18,50', '45,00', '2', '5', 'Estante A'],
        ][$type];

        return response()->streamDownload(function () use ($type, $example) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, CsvImport::COLUMNS[$type], ';');
            fputcsv($out, $example, ';');
            fclose($out);
        }, 'plantilla-' . ($type === 'clients' ? 'clientes' : 'repuestos') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(Request $request, CsvImport $import)
    {
        $validated = $request->validate([
            'type' => ['required', 'in:clients,parts'],
            'file' => ['required', 'file', 'max:4096', 'mimes:csv,txt'],
        ], [], ['type' => 'tipo', 'file' => 'archivo']);

        try {
            $rows = $import->read($request->file('file'));
            $result = $validated['type'] === 'clients'
                ? $import->clients($rows)
                : $import->parts($rows, BranchContext::forCreate(), Auth::id());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('import.index')->with('importResult', $result + ['type' => $validated['type']]);
    }
}
