<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unidad extends Model
{
    use HasFactory;
    use \Awobaz\Compoships\Compoships;

    protected $table = 'unidades';

    protected $fillable = [
        'clues', 'nombre', 'idestado', 'idmunicipio', 'idlocalidad', 'idtipo_unidad', 'idtipologia_unidad', 'idinstitucion', 'idestrato',
        'idtipo_vialidad', 'vialidad', 'idtipo_asentamiento', 'asentamiento', 'nointerior', 'noexterior', 'cp', 'idtipo_administracion',
        'latitud', 'longitud', 'email', 'telefono', 'construccion', 'inicio_operacion', 'idstatus_unidad', 'nconmg', 'nconoa', 'ncamh', 'ncamoa',
        'horarios', 'areas_servicios', 'idmotivo_baja', 'fecha_efectiva_baja','idtipo_establecimiento','idsubtipologia','idtipo_administracion',
        'idnivel_atencion', 'idstatus_propiedad', 'idmotivo_baja', 'fecha_efectiva_baja', 'nombre_responsable', 'pa_responsable', 'sa_responsable',
        'idprofesion', 'cedula_responsable', 'idmarca_um', 'marca_esp_um', 'modelo_um', 'idprograma_um', 'idtipo_um', 'idtipologia_um'
    ];

    protected $primaryKey = 'clues';

    public $incrementing = false;

    public $timestamps = false;

    public function estado()
    {
        return $this->belongsTo(Estado::class,'idestado','idestado');
    }

    public function municipio()
    {
        return $this->belongsTo(Municipio::class,['idestado','idmunicipio'],['idestado','idmunicipio']);
    }

    public function localidad()
    {
        return $this->belongsTo(Localidad::class,['idestado','idmunicipio','idlocalidad'],['idestado','idmunicipio','idlocalidad']);
    }

    public function status_unidad()
    {
        return $this->belongsTo(StatusUnidad::class,'idstatus_unidad','idstatus_unidad');
    }

    public function institucion()
    {
        return $this->belongsTo(Institucion::class,'idinstitucion','idinstitucion');
    }

    public function tipo_unidad()
    {
        return $this->belongsTo(TipoUnidad::class,'idtipo_unidad','idtipo_unidad');
    }

    public function tipo_administracion()
    {
        return $this->belongsTo(TipoAdministracion::class,'idtipo_administracion','idtipo_administracion');
    }

    public function tipologia_unidad()
    {
        return $this->belongsTo(TipologiaUnidad::class,'idtipologia_unidad','idtipologia_unidad');
    }

    public function estrato()
    {
        return $this->belongsTo(Estrato::class,'idestrato','idestrato');
    }

    public function tipo_vialidad()
    {
        return $this->belongsTo(TipoVialidad::class,'idtipo_vialidad','idtipo_vialidad');
    }

    public function tipo_asentamiento()
    {
        return $this->belongsTo(TipoAsentamiento::class,'idtipo_asentamiento','idtipo_asentamiento');
    }

    public function tipo_establecimiento()
    {
        return $this->belongsTo(TipoEstablecimiento::class,'idtipo_establecimiento','idtipo_establecimiento');
    }

    public function subtipologia()
    {
        return $this->belongsTo(Subtipologia::class,'idsubtipologia','idsubtipologia');
    }

    public function nivel_atencion()
    {
        return $this->belongsTo(NivelAtencion::class,'idnivel_atencion','idnivel_atencion');
    }

    public function motivo_baja()
    {
        return $this->belongsTo(MotivoBaja::class,'idmotivo_baja','idmotivo_baja');
    }


}
