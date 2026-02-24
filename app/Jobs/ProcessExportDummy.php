<?php

namespace App\Jobs;

use App\Models\Export;
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
    public int $step;

    public function __construct(int $exportId, int $step)
{
    $this->exportId = $exportId;
    $this->step = $step;
}

    public function handle(): void
{
    // Simular trabajo pesado
    sleep(2);

    logger()->info("Ejecutando Job paso {$this->step}");

}

    public function failed(\Throwable $e): void
    {
        Export::where('id', $this->exportId)->update([
            'status' => 'failed',
            'error' => $e->getMessage(),
        ]);
    }
}