<?php

namespace Database\Seeders;
use App\Models\TipoAdministracion;
use File;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TiposAdministracionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        TipoAdministracion::query()->delete();
        $json = File::get(__DIR__ . '/json/tipos_administracion.json');
        $data = json_decode($json);

        foreach ($data as $item){
            TipoAdministracion::create(array(
                'idtipo_administracion' => $item->idtipo_administracion,
                'tipo_administracion' => $item->tipo_administracion,
            ));
        }
    }
}
