<?php

namespace Database\Seeders;

use App\Models\Menu;
use File;
use Illuminate\Database\Seeder;

class MenusTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Menu::query()->delete();
        $json = File::get(__DIR__.'/json/menus.json');
        $data = json_decode($json);

        foreach ($data as $item) {
            Menu::create([
                'idmenu' => $item->idmenu,
                'menu' => $item->menu,
                'tipo' => $item->tipo,
                'superior' => $item->superior,
                'link' => $item->link,
                'orden' => $item->orden,
                'visible' => $item->visible,
                'newtab' => $item->newtab,
                'icono' => $item->icono,
            ]);
        }
    }
}
