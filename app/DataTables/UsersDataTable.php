<?php

namespace App\DataTables;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class UsersDataTable extends DataTable
{
    protected $permiso = 'usuarios';

    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('Rol', function ($user) {
                if (isset($user->roles->first()->name)) {
                    return ucfirst($user->roles->first()->name);
                } else {
                    return '---';
                }
            })->filterColumn('Rol', function($query, $keyword) {
                $query->whereHas('roles', function($query) use($keyword){
                    return $query->where('name', 'LIKE', '%'.$keyword.'%');
                });
            })->editColumn('idstatus_user', function ($data) {
                return ($data->idstatus_user == 0) ? 'Inactivo' : 'Activo';
            })->filterColumn('idstatus_user', function($query, $keyword) {
                $query->whereRaw('IF(users.idstatus_user=1,"Activo","Inactivo") LIKE ?', ["%{$keyword}%"]);
            })->editColumn('ultimo_acceso', function($data){
                $fecha_acceso = 'Nunca';
                if (!is_null($data->ultimo_acceso)) {
                    $fecha_acceso = Carbon::parse($data->ultimo_acceso)->diffForHumans(['parts' => 6]);
                }
                return '<span data-bs-toggle="tooltip" title="'.$data->ultimo_acceso.'">'.Str::ucfirst($fecha_acceso).'</span>';
            })
            ->addColumn('Acciones', function ($row) {
                $user = Auth()->user();

                $acciones = '';

                if ($user->can('updt_'.$this->permiso)) {
                    $acciones .= ' <a id-accion1="'.$row->id.'" class="modificar"><span data-bs-toggle="tooltip" class="fa fa-edit" title="Modificar"> </span></a> ';
                }
                if ($row->id>1) {
                    if ($user->can('del_'.$this->permiso)) {
                        $acciones .= ' <a id-accion1="'.$row->id.'" class="eliminar"><span data-bs-toggle="tooltip" class="fa fa-trash" title="Eliminar"> </span></a>  ';
                    }
                }
                return $acciones;
            })
            ->rawColumns(['ultimo_acceso', 'Acciones', 'Rol'])
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(User $model): QueryBuilder
    {
        return $model->newQuery()->with('roles')->leftJoin('activity_log', function($query) {
                $query->on('users.id', '=', 'activity_log.causer_id');
            })->select('users.*')->selectRaw('MAX(activity_log.created_at) as ultimo_acceso')
            ->when(!Auth()->user()->hasRole('Administrador General'), function($query) {
                $query->whereDoesntHave('roles', function($query){
                    $query->where('name', 'Administrador General');
                });
            })->groupBy('users.id');
            
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
            ->orderBy(0, 'asc')
            ->selectStyleSingle()
            ->buttons([
                Button::make('excel'),
                Button::make('csv'),
                Button::make('print'),
                Button::make('reload'),
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
                    'sSearch' => 'Buscar:',
                    'sInfoEmpty' => 'No existen resultados para mostrar',
                    'sInfoFiltered' => ' (filtrado de _MAX_ registros en total)',
                    'sLoadingRecords' => 'Por favor espere - cargando...',
                    'sZeroRecords' => 'No existen registros para mostrar',
                    'sEmptyTable' => 'No existe información en la tabla',
                    'sProcessing' => 'Procesando...',
                    'sLengthMenu' => 'Ver <select style="border-radius:5px;">'.
                    '<option value="10">10</option>'.
                    '<option value="20">20</option>'.
                    '<option value="50">50</option>'.
                    '<option value="100">100</option>'.
                    '<option value="-1">Todos</option>'.
                    '</select> Registros&nbsp;',
                    'sInfo' => 'Mostrando _START_ - _END_ de _TOTAL_ registros',
                    'oPaginate' => [
                        'sPrevious' => 'Anterior',
                        'sNext' => 'Siguiente',
                    ],
                    'select' => [
                        'rows' => [
                            '_' => '%d filas seleccionadas',
                            '0' => '',
                            '1' => '%d fila seleccionada',
                        ],
                    ],
                ],
                'drawCallback' => 'function() { var tooltipTriggerList = [].slice.call(document.querySelectorAll(\'[data-bs-toggle="tooltip"]\'))
                    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl, { trigger : \'hover\'})
                    }); }',
            ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('id')->title('ID'),
            Column::make('name')->title('Nombre'),
            Column::make('username')->title('Usuario'),
            Column::make('email')->title('Email'),
            Column::computed('Rol')->searchable(true),
            Column::make('idstatus_user')->title('Status'),
            Column::make('ultimo_acceso')->title('Último acceso')->searchable(false),
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
        return 'Usuarios_'.date('YmdHis');
    }
}
