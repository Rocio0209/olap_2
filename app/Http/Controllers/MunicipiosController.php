<?php

namespace App\Http\Controllers;

use App\DataTables\MunicipiosDataTable;
use Illuminate\Http\Request;
use App\Models\Estado;
use App\Models\Municipio;

class MunicipiosController extends Controller
{
    protected $permiso = 'municipios';

    protected $accion = 'Municipio';

    protected $ao = 'o';

    public function index(MunicipiosDataTable $dataTable, Request $request)
    {
        if ($request->user()->cannot('catalogos/'.$this->permiso)) {
            abort(403);
        }

        $estados=Estado::all();
        return $dataTable->render('catalogos.municipios.index',compact('estados'));
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
            'idestado' => 'required|numeric',
            'idmunicipio' => 'required|numeric',
            'idregional' => 'required|numeric',
            'municipio' => 'required',
        ]);

        $id=null;
        $id2=null;
        if(isset($request->idold)){
            if ($request->user()->cannot('updt_'.$this->permiso)) {
                abort(403,'No tiene permiso para modificar información');
            }

            $id=$request->idold;
        }else{
            if ($request->user()->cannot('add_'.$this->permiso)) {
                abort(403,'No tiene permiso para agregar información');
            }

            $id=$request->idestado;
        }

        if(isset($request->idold2)){
            $id2=$request->idold2;
        }else{
            $id2=$request->idmunicipio;
        }

        Municipio::updateOrInsert([
            'idestado' => $id,
            'idmunicipio' => $id2
        ],
        [
            'idestado' => $request->idestado,
            'idmunicipio' => $request->idmunicipio,
            'idregional' => $request->idregional,
            'municipio' => $request->municipio,
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

        $ids=explode("_",$id);
        $idestado=$ids[0];
        $idmunicipio=$ids[1];

        $elemento = Municipio::whereidestado($idestado)->whereidmunicipio($idmunicipio)->first();
        return response()->json($elemento);
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

        $ids=explode("_",$id);
        $idestado=$ids[0];
        $idmunicipio=$ids[1];

        Municipio::whereidestado($idestado)->whereidmunicipio($idmunicipio)->delete();

        return response()->json(['success'=>$this->accion.' eliminad'.$this->ao.' correctamente.']);
    }

    /***** Obtener Select de los Municipios *****/
    public function getSelect(Request $request){
        if(isset($request->id)){
            $municipios = Municipio::select('idmunicipio','municipio')->whereidestado($request->id)->get();
            return response()->json(
                [
                    'lista' => $municipios,
                    'success' => true
                ]
                );
        }else{
            return response()->json(
                [
                    'success' => false
                ]
                );

        }
    }

    /* Busca los municipios a partir de los regionales */
    public function getSelect_regional(Request $request) {
        if (!$request->user()->canany(['add_unidades', 'updt_unidades'])) {
            abort(403,'No tiene permiso para ver información');
        }

        if(isset($request->estado) && isset($request->regional)){
            $municipios = Municipio::select('idmunicipio','municipio')->where([['idestado', $request->estado], ['idregional', $request->regional]])->get();
            return response()->json([
                    'lista' => $municipios,
                    'success' => true
                ]);
        }

        return response()->json(['success' => false]);
    }
}
