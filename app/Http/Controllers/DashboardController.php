<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Area;
use App\Models\Menu;

class DashboardController extends Controller
{
    public function inicio(Request $request) {
        $rol = $request->user()->roles()->first();
        if (is_null($rol)) { //El usuario no tiene un rol asignado
            return redirect()->route('dashboard');
        }
        $idmenu = $rol->idmenu;
        if (is_null($idmenu)) { //No tiene un menú asignado, entonces se redirecciona al Dashboard
            return redirect()->route('dashboard');
        } else {
            $menu = Menu::findOrFail($idmenu);
            return redirect($menu->link);
        }
    }

    public function index(Request $request, int $fecha_inicio = null, int $fecha_termino = null)
    {
        return view('dashboard');
    }
}
