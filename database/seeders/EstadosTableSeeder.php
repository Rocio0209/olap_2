<?php

namespace Database\Seeders;
use App\Models\Estado;
use File;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EstadosTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Estado::query()->delete();
        $json = File::get(__DIR__ . '/json/estados.json');
        $data = json_decode($json);

        foreach ($data as $item){
            Estado::create(array(
                'idestado' => $item->idestado,
                'estado' => $item->estado,
                'siglas' => $item->siglas,
            ));
        }
    }
}
