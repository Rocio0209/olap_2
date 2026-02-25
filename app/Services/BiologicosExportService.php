<?php

namespace App\Services;

use App\Models\Export;
use App\Exports\BiologicosExport;
use Maatwebsite\Excel\Facades\Excel;

class BiologicosExportService
{
    public function generate(Export $export, array $data): string
    {
        $filename = "exports/biologicos_{$export->id}.xlsx";

        Excel::store(
            new BiologicosExport($data),
            $filename,
            'local'
        );

        return $filename;
    }
}