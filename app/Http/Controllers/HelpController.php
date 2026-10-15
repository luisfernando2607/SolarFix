<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Manuales de uso por rol (resources/manuals/*.md). Cada usuario ve el de su rol;
 * los administradores ven todos. También se descargan en PDF para imprimir o compartir.
 */
class HelpController extends Controller
{
    public const MANUALS = [
        'recepcion' => 'Recepción',
        'tecnico' => 'Técnico',
        'administrador' => 'Administrador',
        'cliente' => 'Guía para el cliente',
    ];

    public function index()
    {
        $available = $this->available();

        return redirect()->route('help.show', array_key_first($available));
    }

    public function show(string $manual)
    {
        $available = $this->available();
        abort_unless(isset($available[$manual]), 404);

        return view('help.show', [
            'manual' => $manual,
            'manuals' => $available,
            'html' => $this->html($manual),
        ]);
    }

    public function pdf(string $manual)
    {
        abort_unless(isset($this->available()[$manual]), 404);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('help.pdf', [
            'title' => self::MANUALS[$manual],
            'html' => $this->html($manual),
            'company' => \App\Support\Company::info(Auth::user()->branch_id),
        ])->stream('manual-' . $manual . '.pdf');
    }

    /**
     * Manuales que puede ver el usuario: el de su rol (y la guía del cliente, que recepción comparte).
     */
    private function available(): array
    {
        $user = Auth::user();
        $keys = match (true) {
            $user->isSuperAdmin(), $user->isAdmin() => array_keys(self::MANUALS),
            $user->isTechnician() => ['tecnico'],
            default => ['recepcion', 'cliente'],
        };

        return array_intersect_key(self::MANUALS, array_flip($keys));
    }

    private function html(string $manual): string
    {
        return Str::markdown(file_get_contents(resource_path("manuals/{$manual}.md")), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
