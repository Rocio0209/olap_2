<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Traits\HasCompositePrimaryKeyTrait;
use App\Models\Estado;

class Municipio extends Model
{
    use HasFactory;
    use HasCompositePrimaryKeyTrait;
    use \Awobaz\Compoships\Compoships;

    protected $table = 'municipios';

    protected $fillable = [
        'idestado', 'idmunicipio', 'idregional'
    ];

    protected $primaryKey = ['idestado', 'idmunicipio'];

    public $timestamps = false;

    public $incrementing = false;

    public function estado()
    {
        return $this->belongsTo(Estado::class,'idestado','idestado');
    }

    public function regional()
    {
        return $this->belongsTo(Regional::class,['idestado','idregional'],['idestado','idregional']);
    }
}
