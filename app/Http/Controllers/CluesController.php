<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class CluesController extends Controller
{
    public function search(Request $request)
    {
        $v = Validator::make($request->all(), [
            'catalogo' => ['required','string'],
            'cubo'     => ['required','string'],
            'estado'   => ['nullable','string'],
            'q'        => ['nullable','string'],
            'limit'    => ['nullable','integer','min:1','max:20'],
            'prefix'   => ['nullable','string','max:10'], // HG / HGIMB / HGSSA
        ]);

        if ($v->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validación fallida',
                'errors' => $v->errors(),
            ], 422);
        }

        $catalogo = $request->string('catalogo')->toString();
        $cubo     = $request->string('cubo')->toString();
        $estado   = $request->input('estado', 'HIDALGO');
        $q        = mb_strtolower(trim((string)$request->input('q','')));
        $limit    = (int)$request->input('limit', 5);
        $prefix   = mb_strtoupper(trim((string)$request->input('prefix','')));

        $baseUrl = rtrim(config('services.vacunas_api.url'), '/');
        $url = $baseUrl . '/clues_y_nombre_unidad_por_estado';

        // ⚠️ pedimos más de 5 al API porque aquí filtraremos por q/prefix
        // (si luego agregas filtros en FastAPI, esto lo bajas a limit directo)
        $resp = Http::timeout(120)
            ->withToken(config('services.vacunas_api.token'))
            ->post($url, [
                'catalogo' => $catalogo,
                'cubo'     => $cubo,
                'estado'   => $estado,
                'max_clues'=> 50000,
            ]);

        if (!$resp->successful()) {
            return response()->json([
                'ok' => false,
                'message' => 'Error consultando CLUES en API',
                'status' => $resp->status(),
                'body' => $resp->body(),
            ], 500);
        }

        $data = $resp->json('data') ?? [];

        // ✅ filtro por prefix
        if ($prefix !== '') {
            $data = array_values(array_filter($data, function($it) use ($prefix) {
                $cl = (string)($it['clues'] ?? '');
                return str_starts_with($cl, $prefix);
            }));
        }

        // ✅ filtro por texto q (clues o nombre_unidad)
        if ($q !== '') {
            $data = array_values(array_filter($data, function($it) use ($q) {
                $cl = mb_strtolower((string)($it['clues'] ?? ''));
                $nm = mb_strtolower((string)($it['nombre_unidad'] ?? ''));
                return str_contains($cl, $q) || str_contains($nm, $q);
            }));
        }

        // ✅ limit 5
        $data = array_slice($data, 0, $limit);

        // ✅ formato para el select
        $items = array_map(function($it) {
            $cl = (string)($it['clues'] ?? '');
            $nm = (string)($it['nombre_unidad'] ?? '');
            return [
                'value' => $cl,
                'label' => $nm ? "{$cl} - {$nm}" : $cl,
            ];
        }, $data);

        return response()->json([
            'ok' => true,
            'items' => $items,
        ]);
    }
}
