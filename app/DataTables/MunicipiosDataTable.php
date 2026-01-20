<?php

namespace App\DataTables;

use App\Models\Municipio;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class MunicipiosDataTable extends DataTable
{
    protected $permiso = 'municipios';

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
                    $acciones.=' <a id-accion1="'.$row->idestado.'" id-accion2="'.$row->idmunicipio.'" class="modificar"><span data-bs-toggle="tooltip" class="fa fa-edit" title="Modificar"> </span></a> ';
                }
                if($user->can('del_'.$this->permiso)){
                    $acciones.= ' <a id-accion1="'.$row->idestado.'" id-accion2="'.$row->idmunicipio.'" class="eliminar"><span data-bs-toggle="tooltip" class="fa fa-trash" title="Eliminar"> </span></a> ';
                }
                return $acciones;
            })
            ->filterColumn('regional', function($query, $keyword) {
                $sql = "regionales.regional  like ?";
                $query->whereRaw($sql, ["%{$keyword}%"]);
            })->orderColumn('regional', function ($query, $order) {
                $query->orderBy('regionales.regional', $order);
            })->orderColumn('idregional', function ($query, $order) {
                $query->orderBy('regionales.regional', $order);
            })
            ->rawColumns(['Acciones'])
            ->setRowId('idmunicipio');
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(Municipio $model): QueryBuilder
    {
        return $model->newQuery()->with(['estado'])
        ->join('regionales', function($join)
        {
            $join->on('regionales.idregional','=','municipios.idregional');
            $join->on('regionales.idestado','=','municipios.idestado');
        });
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
            ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('estado.idestado')->title('IDEstado'),
            Column::make('estado.estado')->title('Estado'),
            Column::make('idregional')->title('IDRegional'),
            Column::make('regional')->title('Regional'),
            Column::make('idmunicipio')->title('IDMunicipio'),
            Column::make('municipio')->title('Municipio'),
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
        return 'Municipios_' . date('YmdHis');
    }
}
