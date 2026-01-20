<?php

namespace Database\Seeders;
use App\Models\ProgramaUM;
use File;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UMProgramasTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        ProgramaUM::query()->delete();
        $json = File::get(__DIR__ . '/json/programas_um.json');
        $data = json_decode($json);

        foreach ($data as $item){
            ProgramaUM::create(array(
                'idprograma_um' => $item->idprograma_um,
                'programa_um' => $item->programa_um,
            ));
        }
    }
}
