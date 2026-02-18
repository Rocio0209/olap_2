<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class PermisosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('permissions')->delete();
        DB::table('roles')->delete();
        DB::table('role_has_permissions')->delete();
        DB::table('model_has_roles')->delete();
        DB::table('model_has_permissions')->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        // create permissions
        Permission::create(['name' => 'dashboard', 'description'=>'Ver Dashboard', 'group'=>'Administración']);
        Permission::create(['name' => 'ver_perfil', 'description'=>'Ver Perfil', 'group'=>'Administración']);
        Permission::create(['name' => 'updt_password', 'description'=>'Actualizar Password', 'group'=>'Administración']);
        Permission::create(['name' => '2factor_auth', 'description'=>'Autenticación de dos factores', 'group'=>'Administración']);
        Permission::create(['name' => 'cerrar_sesiones', 'description'=>'Cerrar Sesiones Remotas', 'group'=>'Administración']);

        Permission::create(['name' => 'catalogos/estados', 'description'=>'Ver Estados', 'group'=>'Estados']);
        Permission::create(['name' => 'add_estados', 'description'=>'Agregar Estados', 'group'=>'Estados']);
        Permission::create(['name' => 'updt_estados', 'description'=>'Actualizar Estados', 'group'=>'Estados']);
        Permission::create(['name' => 'del_estados', 'description'=>'Eliminar Estados', 'group'=>'Estados']);

        Permission::create(['name' => 'catalogos/regionales', 'description'=>'Ver Regionales', 'group'=>'Regionales']);
        Permission::create(['name' => 'add_regionales', 'description'=>'Agregar Regionales', 'group'=>'Regionales']);
        Permission::create(['name' => 'updt_regionales', 'description'=>'Actualizar Regionales', 'group'=>'Regionales']);
        Permission::create(['name' => 'del_regionales', 'description'=>'Eliminar Regionales', 'group'=>'Regionales']);

        Permission::create(['name' => 'catalogos/municipios', 'description'=>'Ver Municipios', 'group'=>'Municipios']);
        Permission::create(['name' => 'add_municipios', 'description'=>'Agregar Municipios', 'group'=>'Municipios']);
        Permission::create(['name' => 'updt_municipios', 'description'=>'Actualizar Municipios', 'group'=>'Municipios']);
        Permission::create(['name' => 'del_municipios', 'description'=>'Eliminar Municipios', 'group'=>'Municipios']);

        Permission::create(['name' => 'catalogos/localidades', 'description'=>'Ver Localidades', 'group'=>'Localidades']);
        Permission::create(['name' => 'add_localidades', 'description'=>'Agregar Localidades', 'group'=>'Localidades']);
        Permission::create(['name' => 'updt_localidades', 'description'=>'Actualizar Localidades', 'group'=>'Localidades']);
        Permission::create(['name' => 'del_localidades', 'description'=>'Eliminar Localidades', 'group'=>'Localidades']);

        Permission::create(['name' => 'catalogos/tipos_unidades', 'description'=>'Ver Tipos Unidades', 'group'=>'Tipos Unidades']);
        Permission::create(['name' => 'add_tipos_unidades', 'description'=>'Agregar Tipos Unidades', 'group'=>'Tipos Unidades']);
        Permission::create(['name' => 'updt_tipos_unidades', 'description'=>'Actualizar Tipos Unidades', 'group'=>'Tipos Unidades']);
        Permission::create(['name' => 'del_tipos_unidades', 'description'=>'Eliminar Tipos Unidades', 'group'=>'Tipos Unidades']);

        Permission::create(['name' => 'catalogos/tipologias_unidades', 'description'=>'Ver Tipologias Unidades', 'group'=>'Tipologias Unidades']);
        Permission::create(['name' => 'add_tipologias_unidades', 'description'=>'Agregar Tipologias Unidades', 'group'=>'Tipologias Unidades']);
        Permission::create(['name' => 'updt_tipologias_unidades', 'description'=>'Actualizar Tipologias Unidades', 'group'=>'Tipologias Unidades']);
        Permission::create(['name' => 'del_tipologias_unidades', 'description'=>'Eliminar Tipologias Unidades', 'group'=>'Tipologias Unidades']);

        Permission::create(['name' => 'catalogos/instituciones', 'description'=>'Ver Instituciones', 'group'=>'Instituciones']);
        Permission::create(['name' => 'add_instituciones', 'description'=>'Agregar Instituciones', 'group'=>'Instituciones']);
        Permission::create(['name' => 'updt_instituciones', 'description'=>'Actualizar Instituciones', 'group'=>'Instituciones']);
        Permission::create(['name' => 'del_instituciones', 'description'=>'Eliminar Instituciones', 'group'=>'Instituciones']);

        Permission::create(['name' => 'unidades', 'description'=>'Ver Unidades', 'group'=>'Unidades']);
        Permission::create(['name' => 'add_unidades', 'description'=>'Agregar Unidades', 'group'=>'Unidades']);
        Permission::create(['name' => 'updt_unidades', 'description'=>'Actualizar Unidades', 'group'=>'Unidades']);
        Permission::create(['name' => 'del_unidades', 'description'=>'Eliminar Unidades', 'group'=>'Unidades']);

        Permission::create(['name' => 'catalogos/estratos', 'description'=>'Ver Estratos', 'group'=>'Estratos']);
        Permission::create(['name' => 'add_estratos', 'description'=>'Agregar Estratos', 'group'=>'Estratos']);
        Permission::create(['name' => 'updt_estratos', 'description'=>'Actualizar Estratos', 'group'=>'Estratos']);
        Permission::create(['name' => 'del_estratos', 'description'=>'Eliminar Estratos', 'group'=>'Estratos']);

        Permission::create(['name' => 'catalogos/tipos_vialidades', 'description'=>'Ver Tipos Vialidades', 'group'=>'Tipos Vialidades']);
        Permission::create(['name' => 'add_tipos_vialidades', 'description'=>'Agregar Tipos Vialidades', 'group'=>'Tipos Vialidades']);
        Permission::create(['name' => 'updt_tipos_vialidades', 'description'=>'Actualizar Tipos Vialidades', 'group'=>'Tipos Vialidades']);
        Permission::create(['name' => 'del_tipos_vialidades', 'description'=>'Eliminar Tipos Vialidades', 'group'=>'Tipos Vialidades']);

        Permission::create(['name' => 'catalogos/tipos_asentamientos', 'description'=>'Ver Tipos Asentamientos', 'group'=>'Tipos Asentamientos']);
        Permission::create(['name' => 'add_tipos_asentamientos', 'description'=>'Agregar Tipos Asentamientos', 'group'=>'Tipos Asentamientos']);
        Permission::create(['name' => 'updt_tipos_asentamientos', 'description'=>'Actualizar Tipos Asentamientos', 'group'=>'Tipos Asentamientos']);
        Permission::create(['name' => 'del_tipos_asentamientos', 'description'=>'Eliminar Tipos Asentamientos', 'group'=>'Tipos Asentamientos']);

        Permission::create(['name' => 'catalogos/tipos_administracion', 'description'=>'Ver Tipos Administración', 'group'=>'Tipos Administración']);
        Permission::create(['name' => 'add_tipos_administracion', 'description'=>'Agregar Tipos Administración', 'group'=>'Tipos Administración']);
        Permission::create(['name' => 'updt_tipos_administracion', 'description'=>'Actualizar Tipos Administración', 'group'=>'Tipos Administración']);
        Permission::create(['name' => 'del_tipos_administracion', 'description'=>'Eliminar Tipos Administración', 'group'=>'Tipos Administración']);

        Permission::create(['name' => 'catalogos/status_unidades', 'description'=>'Ver Estatus Unidades', 'group'=>'Estatus Unidades']);
        Permission::create(['name' => 'add_status_unidades', 'description'=>'Agregar Estatus Unidades', 'group'=>'Estatus Unidades']);
        Permission::create(['name' => 'updt_status_unidades', 'description'=>'Actualizar Estatus Unidades', 'group'=>'Estatus Unidades']);
        Permission::create(['name' => 'del_status_unidades', 'description'=>'Eliminar Estatus Unidades', 'group'=>'Estatus Unidades']);

        Permission::create(['name' => 'configuracion/usuarios', 'description'=>'Ver Usuarios', 'group'=>'Usuarios']);
        Permission::create(['name' => 'add_usuarios', 'description'=>'Agregar Usuarios', 'group'=>'Usuarios']);
        Permission::create(['name' => 'updt_usuarios', 'description'=>'Actualizar Usuarios', 'group'=>'Usuarios']);
        Permission::create(['name' => 'del_usuarios', 'description'=>'Eliminar Usuarios', 'group'=>'Usuarios']);

        Permission::create(['name' => 'configuracion/menus', 'description'=>'Ver Menús', 'group'=>'Menús']);
        Permission::create(['name' => 'add_menus', 'description'=>'Agregar Menús', 'group'=>'Menús']);
        Permission::create(['name' => 'updt_menus', 'description'=>'Actualizar Menús', 'group'=>'Menús']);
        Permission::create(['name' => 'del_menus', 'description'=>'Eliminar Menús', 'group'=>'Menús']);

        Permission::create(['name' => 'configuracion/roles', 'description'=>'Ver Roles', 'group'=>'Roles']);
        Permission::create(['name' => 'add_roles', 'description'=>'Agregar Roles', 'group'=>'Roles']);
        Permission::create(['name' => 'updt_roles', 'description'=>'Actualizar Roles', 'group'=>'Roles']);
        Permission::create(['name' => 'del_roles', 'description'=>'Eliminar Roles', 'group'=>'Roles']);

        Permission::create(['name' => 'configuracion/permisos', 'description'=>'Ver Permisos', 'group'=>'Permisos']);
        Permission::create(['name' => 'add_permisos', 'description'=>'Agregar Permisos', 'group'=>'Permisos']);
        Permission::create(['name' => 'updt_permisos', 'description'=>'Actualizar Permisos', 'group'=>'Permisos']);
        Permission::create(['name' => 'del_permisos', 'description'=>'Eliminar Permisos', 'group'=>'Permisos']);

        Permission::create(['name' => 'vacunas/biologicos', 'description'=>'Ver Biológicos', 'group'=>'Biológicos']);

        // this can be done as separate statements
        $role = Role::create(['name' => 'Administrador General']);
        $role->givePermissionTo(Permission::all());

        $user=User::find(1);
        $user->assignRole($role);

        $role = Role::create(['name' => 'Captura']);
        $role->givePermissionTo(['dashboard', 'ver_perfil', 'updt_password', '2factor_auth', 'cerrar_sesiones']);

    }
}
