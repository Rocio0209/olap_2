<?php

namespace Database\Seeders;
use App\Models\TipoEstablecimiento;
use File;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TiposEstablecimientoTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        TipoEstablecimiento::query()->delete();
        $json = File::get(__DIR__ . '/json/tipos_establecimiento.json');
        $data = json_decode($json);

        foreach ($data as $item){
            TipoEstablecimiento::create(array(
                'idtipo_establecimiento' => $item->idtipo_establecimiento,
                'tipo_establecimiento' => $item->tipo_establecimiento,
            ));
        }
    }
}
