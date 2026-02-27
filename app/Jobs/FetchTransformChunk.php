<?php

namespace App\Jobs;

use App\Models\Export;
use App\Services\VacunasApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Storage;

class FetchTransformChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public int $exportId;
    public array $chunk;
    public int $index;

    public function __construct(int $exportId, array $chunk, int $index)
    {
        $this->exportId = $exportId;
        $this->chunk    = $chunk;
        $this->index    = $index;
    }

    public function handle(VacunasApiService $apiService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $export = Export::find($this->exportId);
        if (!$export) return;
        if ($export->status === 'cancelled') return;

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Consumir API solo con este chunk
        |--------------------------------------------------------------------------
        */

        $params = $export->params;
        $params['clues'] = $this->chunk;

        $data = $apiService->biologicos($params);
        if ($this->batch()?->cancelled()) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Crear carpeta tmp si no existe
        |--------------------------------------------------------------------------
        */

        $tmpPath = "exports/tmp/{$this->exportId}";

        if (!Storage::disk('local')->exists($tmpPath)) {
            Storage::disk('local')->makeDirectory($tmpPath);
        }

        /*
        |--------------------------------------------------------------------------
        | 3️⃣ Guardar archivo part_xxx.jsonl
        |--------------------------------------------------------------------------
        */

        $filename = $tmpPath . '/part_' . str_pad($this->index, 4, '0', STR_PAD_LEFT) . '.jsonl';

        $lines = '';

        foreach ($data['resultados'] ?? [] as $resultado) {
            $lines .= json_encode($resultado, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        }

        Storage::disk('local')->put($filename, $lines);
    }

    public function failed(\Throwable $e): void
    {
        Export::where('id', $this->exportId)->update([
            'status' => 'failed',
            'error'  => $e->getMessage(),
        ]);
    }
}
