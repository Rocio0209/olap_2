<?php

namespace Database\Seeders;
use App\Models\TipoAsentamiento;
use File;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TiposAsentamientosTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        TipoAsentamiento::query()->delete();
        $json = File::get(__DIR__ . '/json/tipos_asentamientos.json');
        $data = json_decode($json);

        foreach ($data as $item){
            TipoAsentamiento::create(array(
                'idtipo_asentamiento' => $item->idtipo_asentamiento,
                'tipo_asentamiento' => $item->tipo_asentamiento,
            ));
        }
    }
}
