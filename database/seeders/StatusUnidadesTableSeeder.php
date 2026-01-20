<?php

namespace Database\Seeders;
use App\Models\StatusUnidad;
use File;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StatusUnidadesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        StatusUnidad::query()->delete();
        $json = File::get(__DIR__ . '/json/status_unidades.json');
        $data = json_decode($json);

        foreach ($data as $item){
            StatusUnidad::create(array(
                'idstatus_unidad' => $item->idstatus_unidad,
                'status_unidad' => $item->status_unidad,
            ));
        }
    }
}
