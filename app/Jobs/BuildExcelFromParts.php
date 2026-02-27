<?php

namespace App\Jobs;

use App\Models\Export;
use App\Exports\BiologicosExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class BuildExcelFromParts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public int $exportId;

    public function __construct(int $exportId)
    {
        $this->exportId = $exportId;
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $export = Export::find($this->exportId);
        if (!$export) return;
        if ($export->status === 'cancelled') return;

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Validar carpeta tmp
        |--------------------------------------------------------------------------
        */

        $tmpPath = "exports/tmp/{$this->exportId}";

        if (!Storage::disk('local')->exists($tmpPath)) {
            throw new \Exception("Carpeta temporal no encontrada.");
        }

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Generar Excel en streaming (sin cargar todo en RAM)
        |--------------------------------------------------------------------------
        */

        $finalPath = "exports/final/biologicos_{$this->exportId}.xlsx";

        Excel::store(
            new BiologicosExport($tmpPath), // 🔥 ahora recibe ruta, no array
            $finalPath,
            'local'
        );
        if ($this->batch()?->cancelled()) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 3️⃣ Eliminar carpeta temporal
        |--------------------------------------------------------------------------
        */

        Storage::disk('local')->deleteDirectory($tmpPath);

        /*
        |--------------------------------------------------------------------------
        | 4️⃣ Actualizar estado
        |--------------------------------------------------------------------------
        */

        if ($export->status !== 'cancelled') {
            $export->update([
                'status'     => 'completed',
                'progress'   => 100,
                'final_path' => $finalPath,
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Export::where('id', $this->exportId)->update([
            'status' => 'failed',
            'error'  => $e->getMessage(),
        ]);
    }
}
