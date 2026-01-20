<?php

namespace Database\Seeders;
use App\Models\TipoUnidad;
use File;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TiposUnidadesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        TipoUnidad::query()->delete();
        $json = File::get(__DIR__ . '/json/tipos_unidades.json');
        $data = json_decode($json);

        foreach ($data as $item){
            TipoUnidad::create(array(
                'idtipo_unidad' => $item->idtipo_unidad,
                'tipo_unidad' => $item->tipo_unidad,
            ));
        }
    }
}
