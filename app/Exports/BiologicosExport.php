<?php

namespace App\Exports;

use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;

class BiologicosExport implements FromGenerator
{
    protected string $tmpPath;

    public function __construct(string $tmpPath)
    {
        $this->tmpPath = $tmpPath;
    }

    public function generator(): Generator
{
    $files = glob(storage_path("app/{$this->tmpPath}/*.jsonl"));
    sort($files);

    $dynamicHeaders = [];

    /*
    |--------------------------------------------------------------------------
    | 1️⃣ Primera pasada: solo construir headers
    |--------------------------------------------------------------------------
    */

    foreach ($files as $file) {

        $handle = fopen($file, 'r');

        while (($line = fgets($handle)) !== false) {

            $decoded = json_decode(trim($line), true);
            if (!$decoded) continue;

            foreach ($decoded['biologicos'] as $bio) {
                foreach ($bio['grupos'] as $grupo) {
                    foreach ($grupo['variables'] as $variable) {

                        $headerKey = $bio['apartado']
                            . ' | '
                            . $grupo['grupo']
                            . ' | '
                            . $variable['variable'];

                        $dynamicHeaders[$headerKey] = $headerKey;
                    }
                }
            }
        }

        fclose($handle);
    }

    $dynamicHeaders = array_values($dynamicHeaders);

    /*
    |--------------------------------------------------------------------------
    | 2️⃣ Yield encabezados
    |--------------------------------------------------------------------------
    */

    yield array_merge(
        [
            'CLUES',
            'Unidad',
            'Entidad',
            'Jurisdicción',
            'Municipio',
            'Institución',
        ],
        $dynamicHeaders
    );

    /*
    |--------------------------------------------------------------------------
    | 3️⃣ Segunda pasada: stream real
    |--------------------------------------------------------------------------
    */

    foreach ($files as $file) {

        $handle = fopen($file, 'r');

        while (($line = fgets($handle)) !== false) {

            $decoded = json_decode(trim($line), true);
            if (!$decoded) continue;

            $row = [
                $decoded['clues'] ?? '',
                $decoded['unidad']['nombre'] ?? '',
                $decoded['unidad']['entidad'] ?? '',
                $decoded['unidad']['jurisdiccion'] ?? '',
                $decoded['unidad']['municipio'] ?? '',
                $decoded['unidad']['institucion'] ?? '',
            ];

            $dynamicValues = array_fill_keys($dynamicHeaders, 0);

            foreach ($decoded['biologicos'] as $bio) {
                foreach ($bio['grupos'] as $grupo) {
                    foreach ($grupo['variables'] as $variable) {

                        $key = $bio['apartado']
                            . ' | '
                            . $grupo['grupo']
                            . ' | '
                            . $variable['variable'];

                        $dynamicValues[$key] = $variable['total'] ?? 0;
                    }
                }
            }

            yield array_merge($row, array_values($dynamicValues));
        }

        fclose($handle);
    }
}
}