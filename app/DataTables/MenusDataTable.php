<?php

namespace App\DataTables;

use App\Models\Menu;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class MenusDataTable extends DataTable
{
    protected $permiso = 'menus';

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
                    $acciones.=' <a id-accion1="'.$row->idmenu.'" class="modificar"><span data-bs-toggle="tooltip" class="fa fa-edit" title="Modificar"> </span></a> ';
                }
                if($user->can('del_'.$this->permiso)){
                    $acciones.= ' <a id-accion1="'.$row->idmenu.'" class="eliminar"><span data-bs-toggle="tooltip" class="fa fa-trash" title="Eliminar"> </span></a> ';
                }
                return $acciones;
            })
            ->editColumn('visible', function ($data) {
                return ($data->visible==0)? 'No' : 'Si';
            })->filterColumn('visible', function ($query, $keyword) {
                $keyword=strtolower($keyword);
                if($keyword==='si' || $keyword==='no'){
                    $query->whereRaw("visible like ?", ($keyword==='si'? 1 : 0));
                }
            })
            ->editColumn('newtab', function ($data) {
                return ($data->newtab==0)? 'No' : 'Si';
            })->filterColumn('newtab', function ($query, $keyword) {
                $keyword=strtolower($keyword);
                if($keyword==='Si' || $keyword==='No'){
                    $query->whereRaw("newtab like ?", ($keyword==='si'? 1 : 0));
                }
            })
            ->editColumn('tipo', function ($data) {
                return ($data->tipo==0)? 'Menú' : 'Submenú';
            })->filterColumn('tipo', function ($query, $keyword) {
                $keyword=strtolower($keyword);
                if($keyword==='menu' || $keyword==='menú' || $keyword==='submenu' || $keyword==='submenú'){
                    $query->whereRaw("tipo like ?", (($keyword==='menu' || $keyword==='menú') ? 0 : 1));
                }
            })->editColumn('icono', function ($data) {
                return is_null($data->icono) ? '' : '<i class="'.$data->icono.'"></i>';
            })->rawColumns(['icono', 'Acciones'])
            ->setRowId('idmenu');
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(Menu $model): QueryBuilder
    {
        return $model->newQuery()->with('superiorName');
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
            Column::make('idmenu')->title('ID'),
            Column::make('menu')->title('Etiqueta'),
            Column::make('tipo')->title('Tipo'),
            Column::make('superior_name')->title('Superior')->data('superior_name.menu')->name('superior_name.menu')->orderable(false)->searchable(false),
            Column::make('link')->title('Link'),
            Column::make('orden')->title('Orden'),
            Column::make('visible')->title('Visible'),
            Column::make('newtab')->title('Nueva Pestaña'),
            Column::make('icono')->title('Ícono'),
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
        return 'Menus_' . date('YmdHis');
    }
}
