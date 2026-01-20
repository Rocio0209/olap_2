<?php

namespace Database\Seeders;
use App\Models\Profesion;
use File;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProfesionesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Profesion::query()->delete();
        $json = File::get(__DIR__ . '/json/profesiones.json');
        $data = json_decode($json);

        foreach ($data as $item){
            Profesion::create(array(
                'idprofesion' => $item->idprofesion,
                'profesion' => $item->profesion,
            ));
        }
    }
}
