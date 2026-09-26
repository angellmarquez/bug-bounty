<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AparienciaController extends Controller
{
    /** Temas visuales disponibles (ver resources/css/app.css). */
    public const TEMAS = ['terminal', 'corporativo', 'neon', 'auto'];

    /**
     * Guarda el tema en la cuenta: así se ve igual en cualquier dispositivo.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tema' => ['required', 'string', Rule::in(self::TEMAS)],
        ], [
            'tema.in' => 'Ese tema no existe.',
        ]);

        $request->user()->update(['tema' => $validated['tema']]);

        return back();
    }
}
