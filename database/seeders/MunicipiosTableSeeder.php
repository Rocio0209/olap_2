<?php

namespace Database\Seeders;
use App\Models\Municipio;
use File;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MunicipiosTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Municipio::query()->delete();
        $json = File::get(__DIR__ . '/json/municipios.json');
        $data = json_decode($json);

        foreach ($data as $item){
            Municipio::create(array(
                'idestado' => $item->idestado,
                'idregional' => $item->idregional,
                'idmunicipio' => $item->idmunicipio,
                'municipio' => $item->municipio,
            ));
        }
    }
}
