<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoAdministracion extends Model
{
    use HasFactory;

    protected $table = 'tipos_administracion';

    protected $fillable = [
        'idtipo_administracion', 'tipo_administracion'
    ];

    protected $primaryKey = 'idtipo_administracion';

    public $timestamps = false;
}
