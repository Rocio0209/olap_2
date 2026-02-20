<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Export;

class ExportsController extends Controller
{
// POST /api/vacunas/exports
public function store(Request $request)
{
    $v = Validator::make($request->all(), [
        'catalogo' => ['required','string'],
        'cubo' => ['required','string'],
        'clues' => ['required','array','min:1'],
        'clues.*' => ['string'],
    ]);

    if ($v->fails()) {
        return response()->json([
            'ok' => false,
            'message' => 'Validación fallida',
            'errors' => $v->errors(),
        ], 422);
    }

    $export = Export::create([
        'type' => 'biologicos',
        'status' => 'queued',
        'progress' => 0,
        'params' => [
            'catalogo' => $request->catalogo,
            'cubo' => $request->cubo,
            'clues' => $request->clues,
        ],
    ]);

    // ✅ AQUÍ MISMO
    \App\Jobs\ProcessExportDummy::dispatch($export->id)->onQueue('exports');

    return response()->json([
        'ok' => true,
        'export' => [
            'id' => $export->id,
            'status' => $export->status,
            'progress' => $export->progress,
        ],
    ]);
}

    // GET /api/vacunas/exports/{id}
    public function show($id)
    {
        $export = Export::findOrFail($id);

        return response()->json([
            'ok' => true,
            'export' => [
                'id' => $export->id,
                'status' => $export->status,
                'progress' => $export->progress,
                'error' => $export->error,
            ],
        ]);
    }
}