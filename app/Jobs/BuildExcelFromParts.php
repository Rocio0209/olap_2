<?php

namespace App\Jobs;

use App\Exceptions\ExportCancelledException;
use App\Models\Export;
use App\Exports\BiologicosExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class BuildExcelFromParts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public int $exportId;

    public function __construct(int $exportId)
    {
        $this->exportId = $exportId;
    }

    public function middleware(): array
    {
        return [new SkipIfBatchCancelled];
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

        try {
            Excel::store(
                new BiologicosExport($tmpPath, $this->exportId),
                $finalPath,
                'local'
            );
        } catch (ExportCancelledException $e) {
            $this->markAsCancelled($export, $tmpPath, $finalPath);
            return;
        }
        if ($this->batch()?->cancelled()) {
            $this->markAsCancelled($export, $tmpPath, $finalPath);
            return;
        }

        $export->refresh();
        if ($export->status === 'cancelled') {
            $this->markAsCancelled($export, $tmpPath, $finalPath);
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

    public function failed(Throwable $e): void
    {
        if ($e instanceof ExportCancelledException) {
            return;
        }

        $export = Export::find($this->exportId);
        if ($export && $export->status === 'cancelled') {
            return;
        }

        Export::where('id', $this->exportId)->update([
            'status' => 'failed',
            'error'  => $e->getMessage(),
        ]);
    }

    protected function markAsCancelled(Export $export, string $tmpPath, string $finalPath): void
    {
        if (Storage::disk('local')->exists($finalPath)) {
            Storage::disk('local')->delete($finalPath);
        }

        if (Storage::disk('local')->exists($tmpPath)) {
            Storage::disk('local')->deleteDirectory($tmpPath);
        }

        $export->update([
            'status' => 'cancelled',
            'progress' => 0,
            'error' => null,
            'final_path' => null,
        ]);
    }
}
