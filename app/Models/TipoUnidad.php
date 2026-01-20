<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoUnidad extends Model
{
    use HasFactory;

    protected $table = 'tipos_unidades';

    protected $fillable = [
        'idtipo_unidad', 'tipo_unidad'
    ];

    protected $primaryKey = 'idtipo_unidad';

    public $timestamps = false;
}
