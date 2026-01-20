<?php

namespace App\Http\Controllers;

use App\DataTables\LocalidadesDataTable;
use Illuminate\Http\Request;
use App\Models\Estado;
use App\Models\Localidad;

class LocalidadesController extends Controller
{
    protected $permiso = 'localidades';

    protected $accion = 'Localidad';

    protected $ao = 'a';

    public function index(LocalidadesDataTable $dataTable, Request $request)
    {
        if ($request->user()->cannot('catalogos/'.$this->permiso)) {
            abort(403);
        }

        $estados=Estado::all();
        return $dataTable->render('catalogos.localidades.index',compact('estados'));
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
            'idestado' => 'required',
            'idmunicipio' => 'required',
            'idlocalidad' => 'required',
            'localidad' => 'required',
        ]);

        $id=null;
        $id2=null;
        $id3=null;
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

        if(isset($request->idold3)){
            $id3=$request->idold3;
        }else{
            $id3=$request->idlocalidad;
        }

        Localidad::updateOrInsert([
            'idestado' => $id,
            'idmunicipio' => $id2,
            'idlocalidad' => $id3
        ],
        [
            'idestado' => $request->idestado,
            'idmunicipio' => $request->idmunicipio,
            'idlocalidad' => $request->idlocalidad,
            'localidad' => $request->localidad,
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
        $idlocalidad=$ids[2];

        $elemento = Localidad::whereidestado($idestado)->whereidmunicipio($idmunicipio)->whereidlocalidad($idlocalidad)->first();
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
        $idlocalidad=$ids[2];

        Localidad::whereidestado($idestado)->whereidmunicipio($idmunicipio)->whereidlocalidad($idlocalidad)->delete();

        return response()->json(['success'=>$this->accion.' eliminad'.$this->ao.' correctamente.']);
    }

    /***** Obtener Select de los Localidades *****/
    public function getSelect(Request $request){
        if(isset($request->id) && isset($request->id2)){
            $localidades = Localidad::select('idlocalidad','localidad')->whereidestado($request->id)->whereidmunicipio($request->id2)->get();
            return response()->json(
                [
                    'lista' => $localidades,
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
}
