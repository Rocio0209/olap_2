<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\RolesDataTable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\Menu;

class RolesController extends Controller
{
    protected $permiso = 'roles';

    protected $accion = 'Rol';

    protected $ao = 'o';

    public function index(RolesDataTable $dataTable, Request $request)
    {
        if ($request->user()->cannot('configuracion/'.$this->permiso)) {
            abort(403);
        }

        $permisos=Permission::all();
        $enlaces_menus = Menu::whereNotNull('link')->where('link', 'NOT LIKE', '%#%')->get();
        return $dataTable->render('configuracion.roles.index',compact('permisos', 'enlaces_menus'));
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
            'name' => 'required',
            'guard_name' => 'required',
            'idmenu' => 'nullable|integer|exists:App\Models\Menu,idmenu'
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

        $role= Role::updateOrCreate([
            'id' => $id
        ],
        [
            'name' => $request->name,
            'guard_name' => $request->guard_name,
            'idmenu' => $request->idmenu
        ]);

        $role->permissions()->sync($request->permissions);

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

        $role = Role::find($id);
        $permisos =  $role->permissions;
        $data=array("role"=>$role,"permisos"=>$permisos);
        return response()->json($data);
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

        Role::find($id)->delete();

        return response()->json(['success'=>$this->accion.' eliminad'.$this->ao.' correctamente.']);
    }
}
