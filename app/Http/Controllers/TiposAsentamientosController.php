<?php

namespace App\Http\Controllers;

use App\DataTables\TiposAsentamientosDataTable;
use App\Models\TipoAsentamiento;
use Illuminate\Http\Request;

class TiposAsentamientosController extends Controller
{
    protected $permiso = 'tipos_asentamientos';

    protected $accion = 'Tipo Asentamiento';

    protected $ao = 'o';

    public function index(TiposAsentamientosDataTable $dataTable, Request $request)
    {
        if ($request->user()->cannot('catalogos/'.$this->permiso)) {
            abort(403);
        }

        return $dataTable->render('catalogos.tipos_asentamientos.index');
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
            'idtipo_asentamiento' => 'required|Integer',
            'tipo_asentamiento' => 'required',
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

        TipoAsentamiento::updateOrCreate([
            'idtipo_asentamiento' => $id
        ],
        [
            'idtipo_asentamiento' => $request->idtipo_asentamiento,
            'tipo_asentamiento' => $request->tipo_asentamiento,
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

        $permiso = TipoAsentamiento::find($id);
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

        TipoAsentamiento::find($id)->delete();

        return response()->json(['success'=>$this->accion.' eliminad'.$this->ao.' correctamente.']);
    }
}
