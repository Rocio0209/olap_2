<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Traits\HasCompositePrimaryKeyTrait;
use App\Models\Estado;
use App\Models\Municipio;

class Localidad extends Model
{
    use HasFactory;
    use HasCompositePrimaryKeyTrait;
    use \Awobaz\Compoships\Compoships;

    protected $table = 'localidades';

    protected $fillable = [
        'idestado', 'idmunicipio', 'idlocalidad', 'localidad'
    ];

    protected $primaryKey = ['idestado', 'idmunicipio', 'idlocalidad'];

    public $timestamps = false;

    public $incrementing = false;

    public function estado()
    {
        return $this->belongsTo(Estado::class,'idestado','idestado');
    }

    public function municipio()
    {
        return $this->belongsTo(Municipio::class,['idestado','idmunicipio'],['idestado','idmunicipio']);
    }

}
