<?php

namespace Database\Seeders;
use App\Models\Institucion;
use File;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InstitucionesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Institucion::query()->delete();
        $json = File::get(__DIR__ . '/json/instituciones.json');
        $data = json_decode($json);

        foreach ($data as $item){
            Institucion::create(array(
                'idinstitucion' => $item->idinstitucion,
                'institucion' => $item->institucion,
                'descripcion_corta' => $item->descripcion_corta,
                'iniciales' => $item->iniciales,
            ));
        }
    }
}
