<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = [
        'idmenu', 'menu', 'tipo', 'superior', 'link', 'orden', 'visible', 'newtab', 'icono',
    ];

    protected $primaryKey = 'idmenu';

    public function getChildren($data, $line)
    {
        $children = [];
        foreach ($data as $line1) {
            if ($line['idmenu'] == $line1['superior']) {
                $children = array_merge($children, [array_merge($line1, ['submenu' => $this->getChildren($data, $line1)])]);
            }
        }

        return $children;
    }

    public function optionsMenu()
    {
        return $this->where('visible', 1)
            ->orderby('superior')
            ->orderby('orden')
            ->get()
            ->toArray();
    }

    public static function menus()
    {
        $menus = new Menu();
        $data = $menus->optionsMenu();
        $menuAll = [];
        foreach ($data as $line) {
            $item = [array_merge($line, ['submenu' => $menus->getChildren($data, $line)])];
            $menuAll = array_merge($menuAll, $item);
        }

        return $menus->menuAll = $menuAll;
    }

    // public function getVisibleStrAttribute()
    // {
    //     return ($this->visible==0)? 'No' : 'Si';
    // }

    // protected $appends = [
    //     'visible_str',
    // ];

    /* Get the superiorName that owns the Menu
    *
    * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
    */
    public function superiorName(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'superior', 'idmenu')->withDefault(['menu' => '---']);
    }
}
