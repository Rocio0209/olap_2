<?php

namespace App\Http\Controllers;

use App\DataTables\UsersDataTable;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UsersController extends Controller
{
    protected $permiso = 'usuarios';

    protected $accion = 'Usuario';

    protected $ao = 'o';

    public function index(UsersDataTable $dataTable, Request $request)
    {
        if ($request->user()->cannot('configuracion/'.$this->permiso)) {
            abort(403);
        }

        $roles = Role::when(!$request->user()->hasRole('Administrador General'), function($query){
            $query->where('name', '!=', 'Administrador General');
        })->get();

        return $dataTable->render('configuracion.users.index', compact('roles'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        request()->validate([
            'idold' => 'nullable|Integer|exists:App\Models\User,id',
            'name' => 'required',
            'username' => ['required', Rule::unique('App\Models\User')->ignore($request->idold)],
            'idstatus_user' => 'required|Integer',
            'idrole' => [
                'required', 
                'Integer', 
                Rule::exists('Spatie\Permission\Models\Role', 'id')->where(function($query) use($request) { 
                    $query->when(!$request->user()->hasRole('Administrador General'), function($query){
                        $query->where('name', '!=', 'Administrador General');
                    }); 
                })
            ],
        ]);

        $fields = [];

        $fields = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'username' => $request->username,
            'idstatus_user' => $request->idstatus_user,
            'changePass' => 0,
        ];

        //verificar si se va a crear o actualizar el registro
        $id = null;
        if (isset($request->idold)) {
            if ($request->user()->cannot('updt_'.$this->permiso)) {
                abort(403, 'No tiene permiso para modificar información');
            }

            $id = $request->idold;
            //en caso de actualizar verificar si se cambiará el password en caso de que no, se elimina
            //de las variables a setear en el update
            if ($request->password === null) {
                unset($fields['password']);
                unset($fields['changePass']);
            }
        } else {
            if ($request->user()->cannot('add_'.$this->permiso)) {
                abort(403, 'No tiene permiso para agregar información');
            }

            request()->validate([
                'password' => 'required',
            ]);
        }

        $user = User::updateOrCreate([
            'id' => $id,
        ],
            $fields
        );

        //Sincronizar rol en caso de que este seteado
        if (isset($request->idrole)) {
            $role_id = $request->idrole;
            $role = Role::where('id', $role_id)->first();
            $user->syncRoles($role);
        }

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
            abort(403, 'No tiene permiso para modificar información');
        }

        $user = User::findOrFail($id);
        $idrol = $user->roles->first();

        return response()->json($user);
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
            abort(403, 'No tiene permiso para eliminar información');
        }

        User::find($id)->delete();

        return response()->json(['success'=>$this->accion.' eliminad'.$this->ao.' correctamente.']);
    }
}
