<?php

namespace App\Http\Controllers;

use App\DataTables\TipologiasUnidadesDataTable;
use App\Models\TipologiaUnidad;
use Illuminate\Http\Request;

class TipologiasUnidadesController extends Controller
{
    protected $permiso = 'tipologias_unidades';

    protected $accion = 'Tipología Unidad';

    protected $ao = 'a';

    public function index(TipologiasUnidadesDataTable $dataTable, Request $request)
    {
        if ($request->user()->cannot('catalogos/'.$this->permiso)) {
            abort(403);
        }

        return $dataTable->render('catalogos.tipologias_unidades.index');
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
            'tipologia_unidad' => 'required',
            'clave_tipologia' => 'required',
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

        TipologiaUnidad::updateOrCreate([
            'idtipologia_unidad' => $id
        ],
        [
            'tipologia_unidad' => $request->tipologia_unidad,
            'clave_tipologia' => $request->clave_tipologia,
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

        $permiso = TipologiaUnidad::find($id);
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

        TipologiaUnidad::find($id)->delete();

        return response()->json(['success'=>$this->accion.' eliminad'.$this->ao.' correctamente.']);
    }
}
