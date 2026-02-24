<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Export;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;
use Throwable;

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
    // \App\Jobs\ProcessExportDummy::dispatch($export->id)->onQueue('exports');

    $batch = Bus::batch([
        new \App\Jobs\ProcessExportDummy($export->id),
    ])
    ->then(function (Batch $batch) use ($export) {
        $export->update([
            'status' => 'completed',
            'progress' => 100,
        ]);
    })
    ->catch(function (Batch $batch, Throwable $e) use ($export) {
        $export->update([
            'status' => 'failed',
            'error' => $e->getMessage(),
        ]);
    })
    ->dispatch();

    $export->update([
        'batch_id' => $batch->id,
        'status' => 'processing',
    ]);

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