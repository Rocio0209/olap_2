<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\MenusDataTable;
use App\Models\Menu;

class MenusController extends Controller
{
    protected $permiso = 'menus';

    protected $accion = 'Menú';

    protected $ao = 'o';

    public function index(MenusDataTable $dataTable, Request $request)
    {
        if ($request->user()->cannot('configuracion/'.$this->permiso)) {
            abort(403);
        }

        return $dataTable->render('configuracion.menus.index');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        request()->validate([
            'etiqueta' => 'required',
            'tipo' => 'required',
            'sup' => 'nullable|required_if:tipo,1|integer|exists:App\Models\Menu,idmenu',
            'url' => 'required',
            'orden' => 'required|integer|min:0',
            'icono' => 'nullable|prohibited_if:tipo,1|string'
        ]);

        $show=0;
        $newlink=0;
        if(isset($request->show)){
            $show=1;
        }
        if(isset($request->newlink)){
            $newlink=1;
        }

        $id=null;
        if(isset($request->idold)){
            if ($request->user()->cannot('updt_'.$this->permiso)) {
                abort(403,'No tiene permiso para modificar información');
            }

            $id=$request->idold;
        }else{
            if ($request->user()->cannot('add_'.$this->permiso)) {
                abort(403,'No tiene permiso para agregar información');
            }

        }

        Menu::updateOrCreate([
            'idmenu' => $id
        ],
        [
            'menu' => $request->etiqueta,
            'tipo' => $request->tipo,
            'superior' => $request->sup,
            'link' => $request->url,
            'orden' => $request->orden,
            'icono' => $request->icono,
            'visible' => $show,
            'newtab' => $newlink,
        ]);

        return response()->json(['success'=>$this->accion.' almacenad'.$this->ao.' correctamente.']);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Model  $element
     * @return \Illuminate\Http\Response
     */
    public function edit($id, Request $request)
    {
        if ($request->user()->cannot('updt_'.$this->permiso)) {
            abort(403,'No tiene permiso para modificar información');
        }

        $menu = Menu::find($id);
        return response()->json($menu);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Model  $element
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        if ($request->user()->cannot('del_'.$this->permiso)) {
            abort(403,'No tiene permiso para eliminar información');
        }

        Menu::find($id)->delete();

        return response()->json(['success'=>$this->accion.' eliminad'.$this->ao.' correctamente.']);
    }
}
