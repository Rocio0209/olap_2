<?php

namespace App\Http\Controllers;

use App\DataTables\TiposVialidadesDataTable;
use App\Models\TipoVialidad;
use Illuminate\Http\Request;

class TiposVialidadesController extends Controller
{
    protected $permiso = 'tipos_vialidades';

    protected $accion = 'Tipo Vialidad';

    protected $ao = 'a';

    public function index(TiposVialidadesDataTable $dataTable, Request $request)
    {
        if ($request->user()->cannot('catalogos/'.$this->permiso)) {
            abort(403);
        }

        return $dataTable->render('catalogos.tipos_vialidades.index');
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
            'idtipo_vialidad' => 'required|Integer',
            'tipo_vialidad' => 'required',
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

        TipoVialidad::updateOrCreate([
            'idtipo_vialidad' => $id
        ],
        [
            'idtipo_vialidad' => $request->idtipo_vialidad,
            'tipo_vialidad' => $request->tipo_vialidad,
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

        $permiso = TipoVialidad::find($id);
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

        TipoVialidad::find($id)->delete();

        return response()->json(['success'=>$this->accion.' eliminad'.$this->ao.' correctamente.']);
    }
}
