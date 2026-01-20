<?php

namespace Database\Seeders;
use App\Models\Subtipologia;
use File;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubtipologiasTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Subtipologia::query()->delete();
        $json = File::get(__DIR__ . '/json/subtipologias.json');
        $data = json_decode($json);

        foreach ($data as $item){
            Subtipologia::create(array(
                'idsubtipologia' => $item->idsubtipologia,
                'subtipologia' => $item->subtipologia,
                'descripcion_subtipologia' => $item->descripcion_subtipologia,
            ));
        }
    }
}
