<?php

namespace Database\Seeders;
use App\Models\StatusPropiedad;
use File;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StatusPropiedadesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        StatusPropiedad::query()->delete();
        $json = File::get(__DIR__ . '/json/status_propiedades.json');
        $data = json_decode($json);

        foreach ($data as $item){
            StatusPropiedad::create(array(
                'idstatus_propiedad' => $item->idstatus_propiedad,
                'status_propiedad' => $item->status_propiedad,
            ));
        }
    }
}
