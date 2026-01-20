<?php

namespace Database\Seeders;
use App\Models\Unidad;
use File;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UnidadesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Unidad::query()->delete();
        $json = File::get(__DIR__ . '/json/unidades.json');
        $data = json_decode($json);

        foreach ($data as $item){
            Unidad::create(array(
                'idestado' => $item->idestado,
                'idmunicipio' => $item->idmunicipio,
                'idlocalidad' => $item->idlocalidad,
                'clues' => $item->clues,
                'idtipo_unidad' => $item->idtipo_unidad,
                'idtipologia_unidad' => $item->idtipologia_unidad,
                'idinstitucion' => $item->idinstitucion,
                'nombre' => $item->nombre,
                'idestrato' => $item->idestrato,
                'idtipo_vialidad' => $item->idtipo_vialidad,
                'vialidad' => $item->vialidad,
                'idtipo_asentamiento' => $item->idtipo_asentamiento,
                'asentamiento' => $item->asentamiento,
                'nointerior' => $item->nointerior,
                'noexterior' => $item->noexterior,
                'cp' => $item->cp,
                'idtipo_administracion' => $item->idtipo_administracion,
                'latitud' => $item->latitud,
                'longitud' => $item->longitud,
                'email' => $item->email,
                'telefono' => $item->telefono,
                'construccion' => $item->construccion,
                'inicio_operacion' => $item->inicio_operacion,
                'idstatus_unidad' => $item->idstatus_unidad,
                'horarios' => $item->horarios,
                'idmotivo_baja' => $item->idmotivo_baja,
                'fecha_efectiva_baja' => $item->fecha_efectiva_baja,
                'idtipo_establecimiento' => $item->idtipo_establecimiento,
                'idsubtipologia' => $item->idsubtipologia,
                'nombre_responsable' => $item->nombre_responsable,
                'pa_responsable' => $item->pa_responsable,
                'sa_responsable' => $item->sa_responsable,
                'idprofesion' => $item->idprofesion,
                'cedula_responsable' => $item->cedula_responsable,
                'idmarca_um' => $item->idmarca_um,
                'marca_esp_um' => $item->marca_esp_um,
                'modelo_um' => $item->modelo_um,
                'idprograma_um' => $item->idprograma_um,
                'idtipo_um' => $item->idtipo_um,
                'idtipologia_um' => $item->idtipologia_um,
                'idnivel_atencion' => $item->idnivel_atencion,
                'idstatus_propiedad' => $item->idstatus_propiedad
            ));
        }
    }
}
