<?php

namespace App\Http\Controllers;

use App\DataTables\RegionalesDataTable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Estado;
use App\Models\Regional;

class RegionalesController extends Controller
{
    protected $permiso = 'regionales';

    protected $accion = 'Coordinador Regional';

    protected $ao = 'o';

    public function index(RegionalesDataTable $dataTable, Request $request)
    {
        if ($request->user()->cannot('catalogos/'.$this->permiso)) {
            abort(403);
        }

        $estados=Estado::all();
        return $dataTable->render('catalogos.regionales.index',compact('estados'));
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
            'idold' => 'nullable|integer|exists:App\Models\Estado,idestado',
            'idold2' => 'nullable|integer|exists:App\Models\Regional,idregional',
            'idestado' => 'required|integer|exists:App\Models\Estado,idestado',
            'idregional' => ['required', 'integer', 'min:1', Rule::unique('App\Models\Regional', 'idregional')->where(function($query) use($request) { $query->where('idestado', $request->idestado); })->ignore($request->idold2, 'idregional')],
            'regional' => 'required|string',
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
            $id2=$request->idregional;
        }

        Regional::updateOrInsert([
            'idestado' => $id,
            'idregional' => $id2
        ],
        [
            'idestado' => $request->idestado,
            'idregional' => $request->idregional,
            'regional' => $request->regional,
        ]);

        return response()->json(['success'=>$this->accion.' almacenad'.$this->ao.' correctamente.']);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Model  $element
     * @return \Illuminate\Http\Response
     */
    public function edit($idestado, $idregional, Request $request)
    {
        if ($request->user()->cannot('updt_'.$this->permiso)) {
            abort(403,'No tiene permiso para modificar información');
        }

        $regional = Regional::where([['idestado', $idestado], ['idregional', $idregional]])->firstOrFail();
        return response()->json($regional);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Model  $element
     * @return \Illuminate\Http\Response
     */
    public function destroy($idestado, $idregional, Request $request)
    {
        if ($request->user()->cannot('del_'.$this->permiso)) {
            abort(403,'No tiene permiso para eliminar información');
        }

        Regional::where([['idestado', $idestado], ['idregional', $idregional]])->delete();

        return response()->json(['success'=>$this->accion.' eliminad'.$this->ao.' correctamente.']);
    }

    /**
     * Busca los Regionales a Partir del Estado.
     *
     * @param  string $campo
     * @param  string $busqueda
     * @return \Illuminate\Http\Response
     */
    public function getSelect(Request $request) {
        if (!$request->user()->canany(['add_unidades', 'updt_unidades'])) {
            abort(403,'No tiene permiso para ver información');
        }

        if(isset($request->id)){
            $regiones = Regional::select('idestado','idregional','regional')->where('idestado', $request->id)->get();
            return response()->json([
                    'lista' => $regiones,
                    'success' => true
                ]);
        }
    }
}
