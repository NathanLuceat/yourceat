<?php

namespace App\Http\Controllers;

use App\Models\WebbliotecaActivity;
use App\Services\WebbliotecaService;
use Illuminate\Http\Request;

class ActivitiesController extends Controller
{
    public function __invoke(Request $request, WebbliotecaService $webblioteca)
    {
        $validated = $request->validate([
            'since_id' => ['sometimes', 'required', 'regex:/^[0-9]+$/D', 'integer', 'min:0'],
        ]);
        $since = (string) ($validated['since_id'] ?? '0');

        $webblioteca->sync();

        // Seleciona os próximos IDs, não os últimos: nenhum evento fica para trás.
        $items = WebbliotecaActivity::where('external_id', '>', $since)
            ->orderBy('external_id')->limit(20)->get()
            ->reverse()->values()
            ->map(fn ($a) => [
                'id' => (string) $a->external_id,
                'text' => $a->text,
                'at' => $a->occurred_at->toIso8601String(),
            ]);

        return response()->json([
            'items' => $items,
            'next_since_id' => (string) ($items->first()['id'] ?? $since),
        ]);
    }
}
