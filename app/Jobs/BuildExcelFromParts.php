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
        $export = Export::find($this->exportId);
        if (!$export) return;

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Leer todos los jsonl
        |--------------------------------------------------------------------------
        */

        $tmpPath = "exports/tmp/{$this->exportId}";

        if (!Storage::disk('local')->exists($tmpPath)) {
            throw new \Exception("Carpeta temporal no encontrada.");
        }

        $files = Storage::disk('local')->files($tmpPath);

        $allResultados = [];

        foreach ($files as $file) {

            $content = Storage::disk('local')->get($file);
            $lines = explode(PHP_EOL, $content);

            foreach ($lines as $line) {

                if (empty(trim($line))) continue;

                $decoded = json_decode($line, true);

                if (is_array($decoded)) {
                    $allResultados[] = $decoded;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Generar Excel final
        |--------------------------------------------------------------------------
        */

        $finalData = [
            'resultados' => $allResultados
        ];

        $finalPath = "exports/final/biologicos_{$this->exportId}.xlsx";

        Excel::store(
            new BiologicosExport($finalData),
            $finalPath,
            'local'
        );

        /*
        |--------------------------------------------------------------------------
        | 3️⃣ Borrar carpeta tmp
        |--------------------------------------------------------------------------
        */

        Storage::disk('local')->deleteDirectory($tmpPath);

        /*
        |--------------------------------------------------------------------------
        | 4️⃣ Actualizar export
        |--------------------------------------------------------------------------
        */

        $export->update([
            'status'     => 'completed',
            'progress'   => 100,
            'final_path' => $finalPath,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Export::where('id', $this->exportId)->update([
            'status' => 'failed',
            'error'  => $e->getMessage(),
        ]);
    }
}