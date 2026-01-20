<?php

namespace App\Http\Controllers;

use App\DataTables\TiposAdministracionDataTable;
use App\Models\TipoAdministracion;
use Illuminate\Http\Request;

class TiposAdministracionController extends Controller
{
    protected $permiso = 'tipos_administracion';

    protected $accion = 'Tipo Administración';

    protected $ao = 'o';

    public function index(TiposAdministracionDataTable $dataTable, Request $request)
    {
        if ($request->user()->cannot('catalogos/'.$this->permiso)) {
            abort(403);
        }

        return $dataTable->render('catalogos.tipos_administracion.index');
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
            'idold' => 'nullable|integer|exists:App\Models\TipoAdministracion,idtipo_administracion',
            'idtipo_administracion' => 'required|integer',
            'tipo_administracion' => 'required',
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

        TipoAdministracion::updateOrCreate([
            'idtipo_administracion' => $id
        ],
        [
            'idtipo_administracion' => $request->idtipo_administracion,
            'tipo_administracion' => $request->tipo_administracion,
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

        $permiso = TipoAdministracion::find($id);
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

        TipoAdministracion::find($id)->delete();

        return response()->json(['success'=>$this->accion.' eliminad'.$this->ao.' correctamente.']);
    }
}
