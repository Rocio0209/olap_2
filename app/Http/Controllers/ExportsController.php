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
    /*
    |--------------------------------------------------------------------------
    | POST /api/vacunas/exports
    |--------------------------------------------------------------------------
    */

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

        /*
        |--------------------------------------------------------------------------
        | Batch
        |--------------------------------------------------------------------------
        | El Job es el que genera el Excel y guarda final_path
        | Aquí ya NO generamos TXT ni tocamos final_path
        */

        $batch = Bus::batch([
            new \App\Jobs\ProcessExportDummy($export->id),
        ])
        ->then(function (Batch $batch) use ($export) {

            // Solo aseguramos estado final
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

    /*
    |--------------------------------------------------------------------------
    | GET /api/vacunas/exports/{id}
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $export = Export::findOrFail($id);

        $progress = $export->progress;

        if ($export->batch_id) {
            $batch = Bus::findBatch($export->batch_id);

            if ($batch) {
                $progress = $batch->progress();

                if ($batch->finished() && !$batch->hasFailures()) {
                    $export->update([
                        'status' => 'completed',
                        'progress' => 100,
                    ]);
                }

                if ($batch->hasFailures()) {
                    $export->update([
                        'status' => 'failed',
                    ]);
                }
            }
        }

        return response()->json([
            'ok' => true,
            'export' => [
                'id' => $export->id,
                'status' => $export->status,
                'progress' => $progress,
                'error' => $export->error,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/vacunas/exports/{id}/download
    |--------------------------------------------------------------------------
    */

    public function download($id)
    {
        $export = Export::findOrFail($id);

        if (!$export->final_path) {
            return response()->json([
                'ok' => false,
                'message' => 'No hay archivo generado.'
            ], 404);
        }

        $fullPath = storage_path('app/' . $export->final_path);

        if (!file_exists($fullPath)) {
            return response()->json([
                'ok' => false,
                'message' => 'Archivo no encontrado.'
            ], 404);
        }

        return response()->download(
            $fullPath,
            basename($export->final_path)
        );
    }
}