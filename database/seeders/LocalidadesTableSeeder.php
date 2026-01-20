<?php

namespace Database\Seeders;
use App\Models\Localidad;
use File;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LocalidadesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Localidad::query()->delete();
        $json = File::get(__DIR__ . '/json/localidades.json');
        $data = json_decode($json);

        foreach ($data as $item){
            Localidad::create(array(
                'idestado' => $item->idestado,
                'idmunicipio' => $item->idmunicipio,
                'idlocalidad' => $item->idlocalidad,
                'localidad' => $item->localidad,
            ));
        }
    }
}
