<?php

namespace Database\Seeders;
use App\Models\TipologiaUM;
use File;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UMTipologiasTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        TipologiaUM::query()->delete();
        $json = File::get(__DIR__ . '/json/tipologias_um.json');
        $data = json_decode($json);

        foreach ($data as $item){
            TipologiaUM::create(array(
                'idtipologia_um' => $item->idtipologia_um,
                'tipologia_um' => $item->tipologia_um,
            ));
        }
    }
}
