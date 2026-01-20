<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoEstablecimiento extends Model
{
    use HasFactory;

    protected $table = 'tipos_establecimiento';

    protected $fillable = [
        'idtipo_establecimiento', 'tipo_establecimiento'
    ];

    protected $primaryKey = 'idtipo_establecimiento';

    public $timestamps = false;

}
