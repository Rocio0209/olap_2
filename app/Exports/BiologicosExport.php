<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class BiologicosExport implements FromArray
{
    protected array $data;

    public function __construct(array $apiResponse)
    {
        $this->data = $apiResponse['resultados'] ?? [];
    }

    public function array(): array
    {
        $rows = [];

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Construir encabezados dinámicos
        |--------------------------------------------------------------------------
        */

        $dynamicHeaders = [];

        foreach ($this->data as $resultado) {
            foreach ($resultado['biologicos'] as $bio) {
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

        $dynamicHeaders = array_values($dynamicHeaders);

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Encabezados finales
        |--------------------------------------------------------------------------
        */

        $headers = array_merge(
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

        $rows[] = $headers;

        /*
        |--------------------------------------------------------------------------
        | 3️⃣ Construir filas
        |--------------------------------------------------------------------------
        */

        foreach ($this->data as $resultado) {

            $row = [];

            // Datos fijos
            $row[] = $resultado['clues'] ?? '';
            $row[] = $resultado['unidad']['nombre'] ?? '';
            $row[] = $resultado['unidad']['entidad'] ?? '';
            $row[] = $resultado['unidad']['jurisdiccion'] ?? '';
            $row[] = $resultado['unidad']['municipio'] ?? '';
            $row[] = $resultado['unidad']['institucion'] ?? '';

            // Inicializar dinámicos en 0
            $dynamicValues = array_fill_keys($dynamicHeaders, 0);

            foreach ($resultado['biologicos'] as $bio) {
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

            $row = array_merge($row, array_values($dynamicValues));

            $rows[] = $row;
        }

        return $rows;
    }
}