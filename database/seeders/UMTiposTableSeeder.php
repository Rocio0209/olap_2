<?php

namespace Database\Seeders;
use App\Models\TipoUM;
use File;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UMTiposTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        TipoUM::query()->delete();
        $json = File::get(__DIR__ . '/json/tipos_um.json');
        $data = json_decode($json);

        foreach ($data as $item){
            TipoUM::create(array(
                'idtipo_um' => $item->idtipo_um,
                'tipo_um' => $item->tipo_um,
            ));
        }
    }
}
