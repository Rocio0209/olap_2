<?php

namespace Database\Seeders;
use App\Models\TipoVialidad;
use File;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TiposVialidadesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        TipoVialidad::query()->delete();
        $json = File::get(__DIR__ . '/json/tipos_vialidades.json');
        $data = json_decode($json);

        foreach ($data as $item){
            TipoVialidad::create(array(
                'idtipo_vialidad' => $item->idtipo_vialidad,
                'tipo_vialidad' => $item->tipo_vialidad,
            ));
        }
    }
}
