<?php

namespace App\Jobs;

use App\Models\Export;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessExportDummy implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $exportId;

    public function __construct(int $exportId)
    {
        $this->exportId = $exportId;
    }

    public function handle(): void
    {
        $export = Export::find($this->exportId);
        if (!$export) return;

        $export->update([
            'status' => 'processing',
            'progress' => 10,
        ]);

        $export->update([
            'progress' => 60,
        ]);

        $export->update([
            'status' => 'completed',
            'progress' => 100,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Export::where('id', $this->exportId)->update([
            'status' => 'failed',
            'error' => $e->getMessage(),
        ]);
    }
}