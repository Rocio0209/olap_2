<?php

namespace Database\Seeders;
use App\Models\MotivoBaja;
use File;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MotivosBajaTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        MotivoBaja::query()->delete();
        $json = File::get(__DIR__ . '/json/motivos_baja.json');
        $data = json_decode($json);

        foreach ($data as $item){
            MotivoBaja::create(array(
                'idmotivo_baja' => $item->idmotivo_baja,
                'motivo_baja' => $item->motivo_baja,
            ));
        }
    }
}
