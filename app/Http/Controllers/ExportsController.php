<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Export;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;
use Throwable;
use App\Jobs\FetchTransformChunk;
use App\Jobs\BuildExcelFromParts;

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

        $clues = $request->clues;
        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Dividir CLUES en chunks de 20
        |--------------------------------------------------------------------------
        */

        $chunks = array_chunk($clues, 20);

        $jobs = [];

        foreach ($chunks as $index => $chunk) {
            $jobs[] = new FetchTransformChunk(
                $export->id,
                $chunk,
                $index
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Agregar Job final que construirá el Excel
        |--------------------------------------------------------------------------
        */

        $jobs[] = new BuildExcelFromParts($export->id);

        /*
        |--------------------------------------------------------------------------
        | 3️⃣ Crear Batch
        |--------------------------------------------------------------------------
        */

        $batch = Bus::batch($jobs)
            ->catch(function (Batch $batch, Throwable $e) use ($export) {
                $export->update([
                    'status' => 'failed',
                    'error'  => $e->getMessage(),
                ]);
            })
            ->dispatch();

        $export->update([
            'batch_id' => $batch->id,
            'status'   => 'processing',
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

    if ($export->status === 'cancelled') {
        return response()->json([
            'ok' => true,
            'export' => [
                'id'       => $export->id,
                'status'   => $export->status,
                'progress' => (int) $export->progress,
                'error'    => $export->error,
            ],
        ]);
    }

    $progress = $export->progress;

    if ($export->batch_id) {

        $batch = Bus::findBatch($export->batch_id);

        if ($batch) {

            $progress = $batch->progress(); // 🔥 progreso real

            if ($batch->cancelled()) {
                $export->update([
                    'status' => 'cancelled',
                ]);
            }

            // Si terminó correctamente
            if ($batch->finished() && !$batch->hasFailures() && $export->status !== 'cancelled') {
                $export->update([
                    'status'   => 'completed',
                    'progress' => 100,
                ]);
            }

            // Si falló
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
            'id'       => $export->id,
            'status'   => $export->status,
            'progress' => (int) $progress,
            'error'    => $export->error,
        ],
    ]);
}

    /*
    |--------------------------------------------------------------------------
    | POST /api/vacunas/exports/{id}/cancel
    |--------------------------------------------------------------------------
    */
    public function cancel($id)
    {
        $export = Export::findOrFail($id);

        if (in_array($export->status, ['completed', 'failed', 'cancelled'], true)) {
            return response()->json([
                'ok' => false,
                'message' => "La exportación ya está en estado '{$export->status}'.",
                'export' => [
                    'id' => $export->id,
                    'status' => $export->status,
                    'progress' => (int) $export->progress,
                ],
            ], 409);
        }

        if ($export->batch_id) {
            $batch = Bus::findBatch($export->batch_id);
            if ($batch && !$batch->cancelled()) {
                $batch->cancel();
            }
        }

        $export->update([
            'status' => 'cancelled',
            'progress' => 0,
            'error' => null,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Exportación cancelada.',
            'export' => [
                'id' => $export->id,
                'status' => $export->status,
                'progress' => (int) $export->progress,
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
