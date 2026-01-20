<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\PermisosDataTable;
use Spatie\Permission\Models\Permission;

class PermisosController extends Controller
{
    protected $permiso = 'permisos';

    protected $accion = 'Persmiso';

    protected $ao = 'o';

    public function index(PermisosDataTable $dataTable, Request $request)
    {
        if ($request->user()->cannot('configuracion/'.$this->permiso)) {
            abort(403);
        }

        return $dataTable->render('configuracion.permisos.index');
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
            'name' => 'required',
            'description' => 'required',
            'guard_name' => 'required',
            'group' => 'required',
        ]);

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

        Permission::updateOrCreate([
            'id' => $id
        ],
        [
            'name' => $request->name,
            'description' => $request->description,
            'guard_name' => $request->guard_name,
            'group' => $request->group,
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

        $permiso = Permission::find($id);
        return response()->json($permiso);
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

        Permission::find($id)->delete();

        return response()->json(['success'=>$this->accion.' eliminad'.$this->ao.' correctamente.']);
    }
}
