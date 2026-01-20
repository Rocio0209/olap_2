<?php

namespace App\DataTables;

use App\Models\Unidad;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class UnidadesDataTable extends DataTable
{
    protected $permiso = 'unidades';

    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('Acciones', function($row){
                $user = Auth()->user();

                $acciones="";
                if($user->can('updt_'.$this->permiso)){
                    $acciones.=' <a id-accion1="'.$row->clues.'" class="modificar"><span data-bs-toggle="tooltip" class="fa fa-edit" title="Modificar"> </span></a> ';
                }
                if($user->can('del_'.$this->permiso)){
                    $acciones.= ' <a id-accion1="'.$row->clues.'" class="eliminar"><span data-bs-toggle="tooltip" class="fa fa-trash" title="Eliminar"> </span></a> ';
                }
                return $acciones;
            })
            ->editColumn('construccion', function($data){
                if($data->construccion!==null){
                    $formatedDate = Carbon::createFromFormat('Y-m-d', $data->construccion)->format('d/m/Y');
                    return $formatedDate;
                }else{ return '---'; }
            })
            ->editColumn('inicio_operacion', function($data){
                if($data->inicio_operacion!==null){
                    $formatedDate = Carbon::createFromFormat('Y-m-d', $data->inicio_operacion)->format('d/m/Y');
                    return $formatedDate;
                }else{ return '---'; }
            })
            ->editColumn('fecha_efectiva_baja', function($data){
                if($data->fecha_efectiva_baja!==null){
                    $formatedDate = Carbon::createFromFormat('Y-m-d', $data->fecha_efectiva_baja)->format('d/m/Y');
                    return $formatedDate;
                }else{ return '---'; }
            })
            ->editColumn('clues',function($data){
                return '<b>'.$data->clues.'</b>';
            })
            ->editColumn('nombre',function($data){
                return '<b>'.$data->nombre.'</b>';
            })
            ->editColumn('status_unidad.status_unidad', function ($data) {
                if(is_string($data->status_unidad)){
                    return '<b>'.$data->status_unidad.'</b>';
                }else{
                    return '<b>'.$data->status_unidad->status_unidad.'</b>';
                }
            })
            ->filterColumn('regional', function($query, $keyword) {
                $sql = "regionales.regional like ?";
                $query->whereRaw($sql, ["%{$keyword}%"]);
            })
            ->orderColumn('regional', function ($query, $order) {
                $query->orderBy('regionales.idregional', $order);
            })
            ->filterColumn('municipio', function($query, $keyword) {
                $sql = "municipios.municipio  like ?";
                $query->whereRaw($sql, ["%{$keyword}%"]);
            })
            ->orderColumn('idmunicipio', function ($query, $order) {
                $query->orderBy('municipios.idmunicipio', $order);
            })
            ->filterColumn('localidad', function($query, $keyword) {
                $sql = "localidades.localidad  like ?";
                $query->whereRaw($sql, ["%{$keyword}%"]);
            })
            ->orderColumn('idlocalidad', function ($query, $order) {
                $query->orderBy('localidades.idlocalidad', $order);
            })
            ->rawColumns(['Acciones','clues','nombre','status_unidad.status_unidad'])
            ->setRowId('clues');
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(Unidad $model): QueryBuilder
    {
        return $model->newQuery()->with(['estado','status_unidad','institucion','tipo_unidad','tipo_administracion',
        'tipologia_unidad','estrato','tipo_vialidad','tipo_asentamiento','tipo_establecimiento','subtipologia',
        'nivel_atencion','motivo_baja'])
        ->join('localidades', function($join)
        {
            $join->on('localidades.idlocalidad','=','unidades.idlocalidad');
            $join->on('localidades.idmunicipio','=','unidades.idmunicipio');
            $join->on('localidades.idestado','=','unidades.idestado');
        })
        ->join('municipios', function($join)
        {
            $join->on('municipios.idmunicipio','=','localidades.idmunicipio');
            $join->on('municipios.idestado','=','localidades.idestado');
        })
        ->join('regionales', function($join)
        {
            $join->on('regionales.idregional','=','municipios.idregional');
            $join->on('regionales.idestado','=','municipios.idestado');
        })
        ->select()
        ->addSelect('regionales.regional','municipios.municipio','localidades.localidad');
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('dataTable-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            //->dom('Bfrtip')
            ->orderBy(0,'asc')
            ->selectStyleSingle()
            ->buttons([
                Button::make('excel'),
                Button::make('csv'),
                Button::make('pdf'),
                Button::make('print'),
                Button::make('reload')
            ])->addTableClass(['table', 'table-striped', 'table-bordered', 'table-hover'])
            ->parameters([
                'layout' => [
                    'top2Start' => 'buttons',
                    'topStart' => 'pageLength',
                    'topEnd' => 'search',
                    'bottomStart' => 'info',
                    'bottomEnd' => 'paging'
                ],
                'responsive' => true,
                'oLanguage' => [
                    "sSearch" => "Buscar:",
                    "sInfoEmpty"=> "No existen resultados para mostrar",
                    "sInfoFiltered" => " (filtrado de _MAX_ registros en total)",
                    "sLoadingRecords" => "Por favor espere - cargando...",
                    "sZeroRecords" => "No existen registros para mostrar",
                    "sEmptyTable" => "No existe información en la tabla",
                    "sProcessing" => "Procesando...",
                    "sLengthMenu" => 'Ver <select style="border-radius:5px;">'.
                    '<option value="10">10</option>'.
                    '<option value="20">20</option>'.
                    '<option value="50">50</option>'.
                    '<option value="100">100</option>'.
                    '<option value="-1">Todos</option>'.
                    '</select> Registros&nbsp;',
                    "sInfo" => "Mostrando _START_ - _END_ de _TOTAL_ registros",
                    "oPaginate" => [
                        "sPrevious" => "Anterior",
                        "sNext" => "Siguiente"
                    ],
                    "select"=>[
                        "rows"=>[
                            "_"=>'%d filas seleccionadas',
                            "0"=>'',
                            "1"=>'%d fila seleccionada',
                        ]
                    ]
                ],
                'drawCallback' => 'function() { var tooltipTriggerList = [].slice.call(document.querySelectorAll(\'[data-bs-toggle="tooltip"]\'))
                    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl)
                    }); }',
                'initComplete'=>'function (){
                    $.when(fillfiltersdrop(this.api())).then(function(){
                        $(\'#f_idstatus_unidad\').val(\'En Operación\').change();
                    });
                }'
            ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('clues')->title('CLUES'),
            Column::make('nombre')->title('Nombre'),
            Column::make('regional')->title('Regional'),
            Column::make('municipio')->title('Municipio'),
            Column::make('localidad')->title('Localidad'),
            Column::make('institucion.institucion')->title('Institución'),
            Column::make('tipo_unidad.tipo_unidad')->title('Tipo Unidad'),
            Column::make('tipo_administracion.tipo_administracion')->title('Tipo Administración'),
            Column::make('nivel_atencion.nivel_atencion')->title('Nivel Atención'),
            Column::make('tipo_establecimiento.tipo_establecimiento')->title('Tipo Establecimiento'),
            Column::make('tipologia_unidad.clave_tipologia')->title('Clave Tipología'),
            Column::make('tipologia_unidad.tipologia_unidad')->title('Tipología'),
            Column::make('subtipologia.subtipologia')->title('Clave Subtipología'),
            Column::make('subtipologia.descripcion_subtipologia')->title('Subtipología'),
            Column::make('estrato.estrato')->title('Estrato'),
            Column::make('tipo_vialidad.tipo_vialidad')->title('Tipo Vialidad'),
            Column::make('vialidad')->title('Vialidad'),
            Column::make('tipo_asentamiento.tipo_asentamiento')->title('Tipo Asentamiento'),
            Column::make('asentamiento')->title('Asentamiento'),
            Column::make('noexterior')->title('No. Exterior'),
            Column::make('nointerior')->title('No. Interior'),
            Column::make('cp')->title('C.P.'),
            Column::make('latitud')->title('Latitud'),
            Column::make('longitud')->title('Longitud'),
            Column::make('email')->title('Email'),
            Column::make('telefono')->title('Teléfono'),
            Column::make('construccion')->title('Construcción'),
            Column::make('inicio_operacion')->title('Inicio de Operación'),
            Column::make('horarios')->title('Horarios'),
            Column::make('motivo_baja.motivo_baja')->title('Motivo Baja'),
            Column::make('fecha_efectiva_baja')->title('Fecha Efectiva de Baja'),
            Column::make('status_unidad.status_unidad')->title('Status')->addClass('all'),
            Column::computed('Acciones')
                  ->exportable(false)
                  ->printable(false)
                  ->width(100)->addClass('all'),
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Unidades_' . date('YmdHis');
    }
}
