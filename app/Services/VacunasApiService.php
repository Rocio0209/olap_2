<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class VacunasApiService
{
    public function biologicos(array $params): array
    {
        $response = Http::baseUrl(config('services.vacunas_api.url'))
            ->withToken(config('services.vacunas_api.token'))
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

        return $response->json();
    }
}