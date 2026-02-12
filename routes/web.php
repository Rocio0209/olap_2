<?php

use App\Http\Controllers\EstadosController;
use App\Http\Controllers\EstratosController;
use App\Http\Controllers\InstitucionesController;
use App\Http\Controllers\RegionalesController;
use App\Http\Controllers\LocalidadesController;
use App\Http\Controllers\MenusController;
use App\Http\Controllers\MunicipiosController;
use App\Http\Controllers\PermisosController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\StatusUnidadesController;
use App\Http\Controllers\TipologiasUnidadesController;
use App\Http\Controllers\TiposAdministracionController;
use App\Http\Controllers\TiposAsentamientosController;
use App\Http\Controllers\TiposUnidadesController;
use App\Http\Controllers\TiposVialidadesController;
use App\Http\Controllers\UnidadesController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentacionDesarrolloController;
use App\Http\Controllers\BiologicosController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect()->route('login'); //Redirigiendo a la pantalla de logueo
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'configUsuario'
])->group(function () {
    Route::get('/inicio', [DashboardController::class, 'inicio'])->name('inicio');
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    /***************************** Configuraciones *****************************/
    /* Route Usuarios  */
    Route::get('/configuracion/usuarios', [UsersController::class, 'index'])->name('users.index');
    Route::resource('/usuarios-api', UsersController::class);

    /* Route Permisos */
    Route::get('/configuracion/permisos', [PermisosController::class, 'index'])->name('permisos.index');
    Route::resource('/permisos-api', PermisosController::class);

    /* Route Roles */
    Route::get('/configuracion/roles', [RolesController::class, 'index'])->name('roles.index');
    Route::resource('/roles-api', RolesController::class);

    /* Route Menús */
    Route::get('/configuracion/menus', [MenusController::class, 'index'])->name('menus.index');
    Route::resource('/menus-api', MenusController::class);

    /***************************** Catálogos *****************************/
    /* Route Estados */
    Route::get('/catalogos/estados', [EstadosController::class, 'index'])->name('estados.index');
    Route::resource('/estados-api', EstadosController::class);

    /* Route Regionales */
    Route::get('/catalogos/regionales', [RegionalesController::class, 'index'])->name('regionales.index');
    Route::resource('/regionales-api', RegionalesController::class);
    Route::get('/catalogos/regionales/{idestado}/{idregional}/edit', [RegionalesController::class, 'edit'])->name('regionales.edit');
    Route::delete('/catalogos/regionales/{idestado}/{idregional}', [RegionalesController::class, 'destroy'])->name('regionales.delete');
    //Obtener Select de Regionales
    Route::post('regionales-select', [RegionalesController::class, 'getSelect']);

    /* Route Municipios */
    Route::get('/catalogos/municipios', [MunicipiosController::class, 'index'])->name('municipios.index');
    Route::resource('/municipios-api', MunicipiosController::class);
    //Obtener Select de municipios
    Route::post('municipios-select', [MunicipiosController::class, 'getSelect']);
    Route::post('municipiosRegional-select', [MunicipiosController::class, 'getSelect_regional']);

    /* Route Localidades */
    Route::get('/catalogos/localidades', [LocalidadesController::class, 'index'])->name('localidades.index');
    Route::resource('localidades-api', LocalidadesController::class);
    //Obtener Select de localidades
    Route::post('localidades-select', [LocalidadesController::class, 'getSelect']);

    /* Route Tipos Unidades */
    Route::get('/catalogos/tipos_unidades', [TiposUnidadesController::class, 'index'])->name('tipos_unidades.index');
    Route::resource('tipos_unidades-api', TiposUnidadesController::class);

    /* Route Tipologias Unidades */
    Route::get('/catalogos/tipologias_unidades', [TipologiasUnidadesController::class, 'index'])->name('tipologias_unidades.index');
    Route::resource('tipologias_unidades-api', TipologiasUnidadesController::class);

    /* Route Instituciones Unidades */
    Route::get('/catalogos/instituciones', [InstitucionesController::class, 'index'])->name('instituciones.index');
    Route::resource('instituciones-api', InstitucionesController::class);

    /* Route Estratos */
    Route::get('/catalogos/estratos', [EstratosController::class, 'index'])->name('estratos.index');
    Route::resource('estratos-api', EstratosController::class);

    /* Route Tipos Vialidades */
    Route::get('/catalogos/tipos_vialidades', [TiposVialidadesController::class, 'index'])->name('tipos_vialidades.index');
    Route::resource('tipos_vialidades-api', TiposVialidadesController::class);

    /* Route Tipos Asentamientos */
    Route::get('/catalogos/tipos_asentamientos', [TiposAsentamientosController::class, 'index'])->name('tipos_asentamientos.index');
    Route::resource('tipos_asentamientos-api', TiposAsentamientosController::class);

    /* Route Tipos Administracion */
    Route::get('/catalogos/tipos_administracion', [TiposAdministracionController::class, 'index'])->name('tipos_administracion.index');
    Route::resource('tipos_administracion-api', TiposAdministracionController::class);

    /* Route Estatus de Unidad */
    Route::get('/catalogos/status_unidades', [StatusUnidadesController::class, 'index'])->name('status_unidades.index');
    Route::resource('status_unidades-api', StatusUnidadesController::class);

    /* Route Unidades */
    Route::get('/unidades', [UnidadesController::class, 'index'])->name('unidades.index');
    Route::resource('unidades-api', UnidadesController::class);

    /* Route Documentación */
    Route::get('/documentacion_desarrollo', [DocumentacionDesarrolloController::class, 'index'])->name('documentacion.index');

    /* Route Biológicos */
    Route::get('/vacunas/biologicos', [BiologicosController::class, 'index'])->name('vacunas.biologicos.index');
});
