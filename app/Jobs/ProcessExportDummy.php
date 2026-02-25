<?php

namespace App\Jobs;

use App\Models\Export;
use App\Services\VacunasApiService;
use App\Services\BiologicosExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;

class ProcessExportDummy implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public int $exportId;

    public function __construct(int $exportId)
    {
        $this->exportId = $exportId;
    }

    public function handle(
        VacunasApiService $apiService,
        BiologicosExportService $exportService
    ): void {
        $export = Export::find($this->exportId);
        if (!$export) return;

        $params = $export->params;

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Consumir API
        |--------------------------------------------------------------------------
        */

        $data = $apiService->biologicos($params);

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Generar Excel
        |--------------------------------------------------------------------------
        */

        $filename = $exportService->generate($export, $data);

        /*
        |--------------------------------------------------------------------------
        | 3️⃣ Actualizar estado
        |--------------------------------------------------------------------------
        */

        $export->update([
            'status'     => 'completed',
            'progress'   => 100,
            'final_path' => $filename,
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