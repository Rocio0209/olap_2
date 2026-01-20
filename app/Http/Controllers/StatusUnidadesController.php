<?php

namespace App\Http\Controllers;

use App\DataTables\StatusUnidadesDataTable;
use Illuminate\Http\Request;
use App\Models\StatusUnidad;

class StatusUnidadesController extends Controller
{
    protected $permiso = 'status_unidades';

    protected $accion = 'Status Unidad';

    protected $ao = 'o';

    public function index(StatusUnidadesDataTable $dataTable, Request $request)
    {
        if ($request->user()->cannot('catalogos/'.$this->permiso)) {
            abort(403);
        }

        return $dataTable->render('catalogos.status_unidades.index');
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
            'idstatus_unidad' => 'required|Integer',
            'status_unidad' => 'required',
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

            $id=$request->idstatus_unidad;
        }

        StatusUnidad::updateOrCreate([
            'idstatus_unidad' => $id
        ],
        [
            'idstatus_unidad' => $request->idstatus_unidad,
            'status_unidad' => $request->status_unidad,
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

        $permiso = StatusUnidad::find($id);
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

        StatusUnidad::find($id)->delete();

        return response()->json(['success'=>$this->accion.' eliminad'.$this->ao.' correctamente.']);
    }
}
