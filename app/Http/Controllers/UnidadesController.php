<?php

namespace App\Http\Controllers;

use App\DataTables\UnidadesDataTable;
use Illuminate\Http\Request;
use App\Models\Estado;
use App\Models\Estrato;
use App\Models\Institucion;
use App\Models\MarcaUM;
use App\Models\MotivoBaja;
use App\Models\NivelAtencion;
use App\Models\Profesion;
use App\Models\ProgramaUM;
use App\Models\StatusPropiedad;
use App\Models\StatusUnidad;
use App\Models\Subtipologia;
use App\Models\TipoAdministracion;
use App\Models\TipoAsentamiento;
use App\Models\TipoEstablecimiento;
use App\Models\TipologiaUM;
use App\Models\TipologiaUnidad;
use App\Models\TipoUM;
use App\Models\TipoUnidad;
use App\Models\TipoVialidad;
use App\Models\Unidad;

class UnidadesController extends Controller
{
    protected $permiso = 'unidades';

    protected $accion = 'Unidad';

    protected $ao = 'a';

    public function index(UnidadesDataTable $dataTable, Request $request)
    {
        if ($request->user()->cannot($this->permiso)) {
            abort(403);
        }

        $estados = Estado::all('idestado', 'estado');
        $tiposunidad = TipoUnidad::all('idtipo_unidad', 'tipo_unidad');
        $tipologiasunidad = TipologiaUnidad::all('idtipologia_unidad', 'tipologia_unidad', 'clave_tipologia');
        $subtipologiasunidad = Subtipologia::all('idsubtipologia', 'subtipologia', 'descripcion_subtipologia');
        $estratos = Estrato::all('idestrato', 'estrato');
        $instituciones = Institucion::all('idinstitucion', 'institucion');
        $tiposadministracion = TipoAdministracion::all('idtipo_administracion', 'tipo_administracion');
        $tiposvialidades = TipoVialidad::all('idtipo_vialidad', 'tipo_vialidad');
        $tiposasentamientos = TipoAsentamiento::all('idtipo_asentamiento', 'tipo_asentamiento');
        $statusunidades = StatusUnidad::all('idstatus_unidad', 'status_unidad');
        $motivosbaja = MotivoBaja::all('idmotivo_baja', 'motivo_baja');
        $tiposestablecimiento = TipoEstablecimiento::all();
        $profesiones = Profesion::all();
        $nivelesatencion = NivelAtencion::all();
        $marcasum = MarcaUM::all();
        $programasum = ProgramaUM::all();
        $tiposum = TipoUM::all();
        $tipologiasum = TipologiaUM::all();
        $statuspropiedad = StatusPropiedad::all();

        return $dataTable->render('catalogos.unidades.index', compact([
            'estados',
            'tiposunidad',
            'tipologiasunidad',
            'estratos',
            'instituciones',
            'tiposadministracion',
            'tiposvialidades',
            'tiposasentamientos',
            'statusunidades',
            'motivosbaja',
            'tiposestablecimiento',
            'subtipologiasunidad',
            'profesiones',
            'nivelesatencion',
            'marcasum',
            'programasum',
            'tiposum',
            'tipologiasum',
            'statuspropiedad'
        ]));
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
            'clues' => 'required|max:13',
            'nombre' => 'required',
            'idestado' => 'required|numeric',
            'idmunicipio' => 'required|numeric',
            'idlocalidad' => 'required|numeric',
            'idtipo_unidad' => 'required|Integer',
            'idtipologia_unidad' => 'required|Integer',
            'idinstitucion' => 'required|numeric',
            'idestrato' => 'required|Integer',
            'idtipo_vialidad' => 'required|Integer',
            'vialidad' => 'required',
            'idtipo_asentamiento' => 'required|Integer',
            'asentamiento' => 'required',
            'idtipo_administracion' => 'required|Integer',
            'idstatus_unidad' => 'required|Integer',
        ]);

        $id = null;
        if (isset($request->idold)) {
            if ($request->user()->cannot('updt_' . $this->permiso)) {
                abort(403, 'No tiene permiso para modificar información');
            }

            $id = $request->idold;
        } else {
            if ($request->user()->cannot('add_' . $this->permiso)) {
                abort(403, 'No tiene permiso para agregar información');
            }

            $id = $request->clues;
        }

        Unidad::updateOrCreate(
            [
                'clues' => $id
            ],
            [
                'clues' => $request->clues,
                'nombre' => $request->nombre,
                'idestado' => $request->idestado,
                'idmunicipio' => $request->idmunicipio,
                'idlocalidad' => $request->idlocalidad,
                'idtipo_unidad' => $request->idtipo_unidad,
                'idtipo_establecimiento' => $request->idtipo_establecimiento,
                'idtipologia_unidad' => $request->idtipologia_unidad,
                'idsubtipologia' => $request->idsubtipologia,
                'idinstitucion' => $request->idinstitucion,
                'idestrato' => $request->idestrato,
                'idtipo_administracion' => $request->idtipo_administracion,
                'idnivel_atencion' => $request->idnivel_atencion,
                'idstatus_propiedad' => $request->idstatus_propiedad,
                'idtipo_vialidad' => $request->idtipo_vialidad,
                'vialidad' => $request->vialidad,
                'idtipo_asentamiento' => $request->idtipo_asentamiento,
                'asentamiento' => $request->asentamiento,
                'nointerior' => $request->nointerior,
                'noexterior' => $request->noexterior,
                'cp' => $request->cp,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
                'email' => $request->email,
                'telefono' => $request->telefono,
                'construccion' => $request->construccion,
                'inicio_operacion' => $request->inicio_operacion,
                'idstatus_unidad' => $request->idstatus_unidad,
                'horarios' => $request->horarios,
                'idmotivo_baja' => $request->idmotivo_baja,
                'fecha_efectiva_baja' => $request->fecha_efectiva_baja,
                'nombre_responsable' => $request->nombre_responsable,
                'pa_responsable' => $request->pa_responsable,
                'sa_responsable' => $request->sa_responsable,
                'idprofesion' => $request->idprofesion,
                'cedula_responsable' => $request->cedula_responsable,
                'idmarca_um' => $request->idmarca_um,
                'marca_esp_um' => $request->marca_esp_um,
                'modelo_um' => $request->modelo_um,
                'idprograma_um' => $request->idprograma_um,
                'idtipo_um' => $request->idtipo_um,
                'idtipologia_um' => $request->idtipologia_um,
            ]
        );

        return response()->json(['success' => $this->accion . ' almacenad' . $this->ao . ' correctamente.']);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Model  $element
     * @return \Illuminate\Http\Response
     */
    public function edit($id, Request $request)
    {
        if ($request->user()->cannot('updt_' . $this->permiso)) {
            abort(403, 'No tiene permiso para modificar información');
        }

        $permiso = Unidad::with(['municipio'])->find($id);
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
        if ($request->user()->cannot('del_' . $this->permiso)) {
            abort(403, 'No tiene permiso para eliminar información');
        }

        Unidad::find($id)->delete();

        return response()->json(['success' => $this->accion . ' eliminad' . $this->ao . ' correctamente.']);
    }
}
