<?php

namespace Database\Seeders;

use File;
use Illuminate\Database\Seeder;
use App\Models\Regional;

class RegionalesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Regional::query()->delete();
        $json = File::get(__DIR__ . '/json/regionales.json');
        $data = json_decode($json);

        foreach ($data as $item){
            Regional::create(array(
                'idestado' => $item->idestado,
                'idregional' => $item->idregional,
                'regional' => $item->regional,
            ));
        }
    }
}
