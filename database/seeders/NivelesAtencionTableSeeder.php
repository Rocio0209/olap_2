<?php

namespace Database\Seeders;
use App\Models\NivelAtencion;
use File;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NivelesAtencionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        NivelAtencion::query()->delete();
        $json = File::get(__DIR__ . '/json/niveles_atencion.json');
        $data = json_decode($json);

        foreach ($data as $item){
            NivelAtencion::create(array(
                'idnivel_atencion' => $item->idnivel_atencion,
                'nivel_atencion' => $item->nivel_atencion,
            ));
        }
    }
}
