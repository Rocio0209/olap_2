<?php

namespace Database\Seeders;
use App\Models\TipologiaUnidad;
use File;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TipologiasUnidadesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        TipologiaUnidad::query()->delete();
        $json = File::get(__DIR__ . '/json/tipologias_unidades.json');
        $data = json_decode($json);

        foreach ($data as $item){
            TipologiaUnidad::create(array(
                'idtipologia_unidad' => $item->idtipologia_unidad,
                'tipologia_unidad' => $item->tipologia_unidad,
                'clave_tipologia' => $item->clave_tipologia,
            ));
        }
    }
}
