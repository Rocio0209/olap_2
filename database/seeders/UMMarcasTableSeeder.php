<?php

namespace Database\Seeders;
use App\Models\MarcaUM;
use File;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UMMarcasTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        MarcaUM::query()->delete();
        $json = File::get(__DIR__ . '/json/marcas_um.json');
        $data = json_decode($json);

        foreach ($data as $item){
            MarcaUM::create(array(
                'idmarca_um' => $item->idmarca_um,
                'marca_um' => $item->marca_um,
            ));
        }
    }
}
