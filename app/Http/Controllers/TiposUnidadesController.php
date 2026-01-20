<?php

namespace App\Http\Controllers;

use App\DataTables\TiposUnidadesDataTable;
use Illuminate\Http\Request;
use App\Models\TipoUnidad;

class TiposUnidadesController extends Controller
{
    protected $permiso = 'tipos_unidades';

    protected $accion = 'Tipo Unidad';

    protected $ao = 'a';

    public function index(TiposUnidadesDataTable $dataTable, Request $request)
    {
        if ($request->user()->cannot('catalogos/'.$this->permiso)) {
            abort(403);
        }

        return $dataTable->render('catalogos.tipos_unidades.index');
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
            'tipo_unidad' => 'required',
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

        TipoUnidad::updateOrCreate([
            'idtipo_unidad' => $id
        ],
        [
            'tipo_unidad' => $request->tipo_unidad,
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

        $permiso = TipoUnidad::find($id);
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

        TipoUnidad::find($id)->delete();

        return response()->json(['success'=>$this->accion.' eliminad'.$this->ao.' correctamente.']);
    }
}
