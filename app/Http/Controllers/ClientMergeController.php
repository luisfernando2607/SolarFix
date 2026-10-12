<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Services\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Clientes duplicados: detección (mismo teléfono o cédula) y unión en un solo registro.
 */
class ClientMergeController extends Controller
{
    /**
     * Tablas con client_id que pasan al cliente que se conserva.
     */
    private const RELATED_TABLES = ['orders', 'appointments', 'maintenance_plans', 'invoices', 'whatsapp_logs', 'sales'];

    public function duplicates()
    {
        // Primera pasada liviana (solo id, teléfono y cédula, por bloques); después se cargan
        // completos únicamente los clientes que están repetidos.
        $keys = ['teléfono' => [], 'cédula' => []];
        Client::select(['id', 'phone', 'id_document'])->lazyById(2000)->each(function ($c) use (&$keys) {
            if ($phone = WhatsAppMessage::normalizePhone($c->phone)) {
                $keys['teléfono'][$phone][] = $c->id;
            }
            if ($document = preg_replace('/\D/', '', (string) $c->id_document)) {
                $keys['cédula'][$document][] = $c->id;
            }
        });

        $repeated = collect($keys)->map(fn ($values) => array_filter($values, fn ($ids) => count($ids) > 1));
        $clients = Client::withCount('orders')
            ->whereIn('id', $repeated->flatten()->unique()->values())
            ->get(['id', 'name', 'phone', 'id_document', 'email', 'created_at'])
            ->keyBy('id');

        $groups = collect();
        foreach ($repeated as $reason => $values) {
            foreach ($values as $value => $ids) {
                $groups->push(['reason' => $reason, 'value' => (string) $value, 'clients' => $clients->only($ids)->sortKeys()->values()]);
            }
        }

        return view('clients.duplicates', ['groups' => $groups]);
    }

    /**
     * Desde la ficha del cliente: une $client (se elimina) en el cliente elegido.
     */
    public function merge(Request $request, Client $client)
    {
        $validated = $request->validate(['target_id' => ['required', 'integer', 'exists:clients,id']]);
        $target = Client::findOrFail($validated['target_id']);
        abort_if($target->id === $client->id, 422, 'Elige un cliente distinto.');

        $this->mergeInto($client, $target);

        return redirect()->route('clients.show', $target)
            ->with('success', "Se unió «{$client->name}» a «{$target->name}» con todo su historial.");
    }

    /**
     * Desde la lista de duplicados: conserva uno y une los demás del grupo.
     */
    public function mergeGroup(Request $request)
    {
        $validated = $request->validate([
            'target_id' => ['required', 'integer', 'exists:clients,id'],
            'source_ids' => ['required', 'array', 'min:1'],
            'source_ids.*' => ['integer', 'exists:clients,id'],
        ]);
        $target = Client::findOrFail($validated['target_id']);
        $sources = Client::whereIn('id', $validated['source_ids'])->whereKeyNot($target->id)->get();

        foreach ($sources as $source) {
            $this->mergeInto($source, $target);
        }

        return redirect()->route('clients.duplicates')
            ->with('success', "Se unieron {$sources->count()} registros en «{$target->name}».");
    }

    /**
     * Mueve el historial de $source a $target, completa los datos vacíos y elimina $source.
     */
    private function mergeInto(Client $source, Client $target): void
    {
        DB::transaction(function () use ($source, $target) {
            $moved = [];
            foreach (self::RELATED_TABLES as $table) {
                $moved[$table] = DB::table($table)->where('client_id', $source->id)->update(['client_id' => $target->id]);
            }

            foreach (['email', 'address', 'id_document'] as $field) {
                if (blank($target->{$field}) && filled($source->{$field})) {
                    $target->{$field} = $source->{$field};
                }
            }
            if (filled($source->notes)) {
                $target->notes = trim($target->notes . "\n" . $source->notes);
            }
            $target->save();
            $source->delete();

            AuditLog::record('merged', $target, ['cliente_unido' => $source->name . ' (#' . $source->id . ')'], $moved,
                description: 'Cliente ' . $source->name . ' unido a ' . $target->name);
        });
    }
}
