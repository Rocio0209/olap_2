<?php

namespace Database\Seeders;
use App\Models\Estrato;
use File;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EstratosTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Estrato::query()->delete();
        $json = File::get(__DIR__ . '/json/estratos.json');
        $data = json_decode($json);

        foreach ($data as $item){
            Estrato::create(array(
                'idestrato' => $item->idestrato,
                'estrato' => $item->estrato,
            ));
        }
    }
}
