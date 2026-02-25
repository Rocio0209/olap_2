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
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Http;

class ProcessExportDummy implements ShouldQueue
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

        $params = $export->params;

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Consumir API externa correctamente
        |--------------------------------------------------------------------------
        */

        $response = Http::baseUrl(config('services.vacunas_api.url'))
    ->withToken(config('services.vacunas_api.token')) // 👈 ESTA LÍNEA FALTA
    ->timeout(300)
    ->acceptJson()
    ->post(config('services.vacunas_api.endpoints.biologicos'), [
        'catalogo'      => $params['catalogo'],
        'cubo'          => $params['cubo'],
        'clues_list'    => $params['clues'],
        'search_text'   => 'APLICACIÓN DE BIOLÓGICOS',
        'max_vars'      => 5000,
        'incluir_ceros' => true,
    ]);

        if ($response->status() === 401) {
            throw new \Exception('Token inválido o expirado (401)');
        }

        if ($response->failed()) {
            throw new \Exception(
                'Error API: ' . $response->status() . ' - ' . $response->body()
            );
        }

        $data = $response->json();

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Generar Excel
        |--------------------------------------------------------------------------
        */

        $filename = "exports/biologicos_{$export->id}.xlsx";

        Excel::store(
            new BiologicosExport($data),
            $filename,
            'local'
        );

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