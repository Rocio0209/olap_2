<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class BiologicosController extends Controller
{
    public function index()
    {
        return view('vacunas.biologicos');
    }

    public function preview(Request $request)
    {
        $v = Validator::make($request->all(), [
            'catalogo' => ['required', 'string'],
            'cubo'     => ['nullable', 'string'],

            // Puedes mandar:
            // - clues: ["HGSSA...","HGIMB..."]
            // o filtro por prefijo:
            // - clues_filter: { "type": "prefix", "value": "HG" }
            'clues' => ['nullable', 'array'],
            'clues.*' => ['string'],

            'clues_filter' => ['nullable', 'array'],
            'clues_filter.type' => ['nullable', 'string', 'in:prefix'],
            'clues_filter.value' => ['nullable', 'string'],
            'search_text' => ['nullable', 'string'],
            'max_vars' => ['nullable', 'integer'],
            'incluir_ceros' => ['nullable', 'boolean'],

        ]);

        if ($v->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validación fallida',
                'errors' => $v->errors(),
            ], 422);
        }

        $catalogo = $request->string('catalogo')->toString();
        $cubo     = $request->input('cubo');
        $limit    = 10;

        // ✅ Define $clues (antes no existía)
        $clues = $request->input('clues', []);

        // Si NO vienen clues, podrías soportar clues_filter aquí después.
        if (empty($clues)) {
            return response()->json([
                'ok' => true,
                'summary' => [
                    'total_clues' => 0,
                    'total_rows' => 0,
                    'preview_rows' => 0,
                    'message' => 'No hay CLUES para consultar.',
                ],
                'table' => [
                    'header_levels' => $this->defaultHeaderLevels(),
                    'rows' => [],
                ],
            ]);
        }

        // ✅ Preview: solo 10 clues
        $cluesPreview = array_slice($clues, 0, $limit);

        // ✅ Llama la API SOLO con las 10 clues del preview
        $apiResponse = $this->fetchCluesBiologicos(
            catalogo: $catalogo,
            cubo: $cubo,
            clues: $cluesPreview
        );

        // ✅ Aplana a filas tabulares
        $flatRows = $this->flattenApiResponseToRows($apiResponse);

        // ✅ Preview: máximo 10 filas
        $previewRows = array_slice($flatRows, 0, $limit);

        // ✅ Summary (por ahora total_rows es sobre lo que trajiste en preview)
        $summary = [
            'total_clues'   => count($clues),
            'total_rows'    => count($flatRows),
            'preview_rows'  => count($previewRows),
            'message'       => 'Preview generado correctamente (10 filas).',
        ];

        return response()->json([
            'ok' => true,
            'summary' => $summary,
            'table' => [
                'header_levels' => $this->defaultHeaderLevels(),
                'rows' => $previewRows,
            ],
        ]);
    }

    /**
     * ✅ Stub temporal: aquí conectas tu API real.
     * Debe regresar un array como el JSON que ya tienes.
     */
    private function fetchCluesBiologicos(
        string $catalogo,
        string $cubo,
        array $clues,
        string $searchText = 'APLICACIÓN DE BIOLÓGICOS',
        int $maxVars = 5000,
        bool $incluirCeros = true
    ): array {
        $baseUrl = rtrim(config('services.vacunas_api.url'), '/');
        $url = $baseUrl . '/biologicos_por_clues_con_unidad';

        $payload = [
            'catalogo' => $catalogo,
            'cubo' => $cubo,
            'clues_list' => array_values($clues),  // 👈 clave correcta
            'search_text' => $searchText,
            'max_vars' => $maxVars,
            'incluir_ceros' => $incluirCeros,
        ];

        $response = Http::timeout(120)
            ->withToken(config('services.vacunas_api.token'))
            ->post($url, $payload);

        if (!$response->successful()) {
            throw new \RuntimeException("Error API: {$response->status()} - {$response->body()}");
        }

        return $response->json();
    }


    /**
     * ✅ Stub temporal: convierte el JSON a filas para tabla.
     * En preview normalmente quieres 1 fila por CLUES (no por variable).
     */
    private function flattenApiResponseToRows(array $apiResponse): array
{
    $resultados = $apiResponse['resultados'] ?? [];
    if (!is_array($resultados)) return [];

    $rows = [];

    foreach ($resultados as $r) {
        $u = $r['unidad'] ?? [];

        $row = [
            'clues' => $r['clues'] ?? '',
            'unidad_nombre' => $u['nombre'] ?? '',
            'entidad' => $u['entidad'] ?? '',
            'jurisdiccion' => $u['jurisdiccion'] ?? '',
            'municipio' => $u['municipio'] ?? '',
            'institucion' => $u['institucion'] ?? '',
        ];

        foreach (($r['biologicos'] ?? []) as $apartado) {
            $apartadoNombre = (string) ($apartado['apartado'] ?? 'SIN_APARTADO');
            $apartadoKey = $this->makeKey($apartadoNombre);

            if (!isset($row[$apartadoKey])) {
                $row[$apartadoKey] = [];
            }

            foreach (($apartado['grupos'] ?? []) as $grupo) {
                foreach (($grupo['variables'] ?? []) as $var) {
                    $varNombre = (string) ($var['variable'] ?? 'SIN_VARIABLE');
                    $varKey = $this->makeKey($varNombre);
                    $total = (int) ($var['total'] ?? 0);

                    // ✅ Como ignoras grupos, sumamos si se repite
                    $row[$apartadoKey][$varKey] = ($row[$apartadoKey][$varKey] ?? 0) + $total;
                }
            }
        }

        $rows[] = $row;
    }

    return $rows;
}

    /**
     * ✅ Headers mínimos (para que el front tenga columnas fijas).
     * Luego lo ajustamos al layout tipo Excel (BCG, etc.).
     */
    private function defaultHeaderLevels(): array
    {
        return [
            [
                ['key' => 'clues', 'label' => 'CLUES'],
                ['key' => 'unidad_nombre', 'label' => 'NOMBRE DE LA UNIDAD'],
                ['key' => 'entidad', 'label' => 'ENTIDAD'],
                ['key' => 'jurisdiccion', 'label' => 'JURISDICCIÓN'],
                ['key' => 'municipio', 'label' => 'MUNICIPIO'],
                ['key' => 'institucion', 'label' => 'INSTITUCIÓN'],
            ]
        ];
    }
    private function makeKey(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/u', '_', $text);
        return trim($text, '_');
    }

}
